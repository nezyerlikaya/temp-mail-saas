<?php

namespace App\Services\Analytics;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class AnalyticsReadinessService extends Service
{
    public function __construct(
        private readonly ConversionFunnelReadinessService $conversion,
        private readonly UserJourneyReadinessService $journey,
        private readonly RetentionReadinessService $retention,
        private readonly AnalyticsCertificationService $certification,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('analytics_review_started');

        $analytics = $this->analyticsReview();
        $conversion = $this->conversion->review();
        $journey = $this->journey->review();
        $retention = $this->retention->review();
        $certification = $this->certification->certify($analytics);
        $sections = compact('analytics', 'conversion', 'journey', 'retention');
        $blockers = $this->issues($sections, 'blockers');
        $warnings = [
            ...$this->issues($sections, 'warnings'),
            ...$certification['warnings'],
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record('analytics_review_'.$status, $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
            'certification' => $certification['status'],
        ]);

        if ($certification['status'] === 'certified') {
            $this->record('analytics_certified');
        }

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([...$blockers, ...$warnings])->pluck('message')->unique()->values()->all(),
            'certification' => $certification,
            'sections' => $sections,
        ];
    }

    private function analyticsReview(): array
    {
        $checks = [
            $this->check('event_readiness', (bool) config('analytics.readiness.events_ready', true) && Schema::hasTable('operations_events'), 'Analytics event readiness is available.', 'Analytics event readiness needs review.', 'blocker'),
            $this->check('metrics_readiness', (bool) config('analytics.readiness.metrics_ready', true) && Schema::hasTable('queue_metrics'), 'Aggregate metrics readiness is available.', 'Aggregate metrics readiness needs review.', 'warning'),
            $this->check('reporting_readiness', (bool) config('analytics.readiness.reporting_ready', true), 'Reporting readiness is documented.', 'Reporting readiness needs review.', 'warning'),
            $this->check('privacy_readiness', (bool) config('analytics.readiness.privacy_ready', true) && $this->privacySafe(), 'Analytics privacy guardrails are enabled.', 'Analytics privacy guardrails need review.', 'blocker'),
        ];

        return $this->summarize($checks);
    }

    private function privacySafe(): bool
    {
        return ! (bool) config('analytics.privacy.allow_personal_profiles', false)
            && ! (bool) config('analytics.privacy.allow_fingerprinting', false)
            && ! (bool) config('analytics.privacy.allow_mailbox_content', false)
            && ! (bool) config('analytics.privacy.allow_email_addresses', false)
            && ! (bool) config('analytics.privacy.external_providers_enabled', false);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocker')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function issues(array $sections, string $type): array
    {
        return collect($sections)
            ->flatMap(fn (array $section, string $category): array => collect($section[$type])
                ->map(fn (array $issue): array => ['category' => $category, ...$issue])
                ->all())
            ->values()
            ->all();
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'analytics-readiness',
            'Analytics readiness event recorded.',
            $metadata,
        );
    }
}
