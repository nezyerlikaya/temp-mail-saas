<?php

namespace App\Services\Operations;

use App\Enums\BillingWebhookStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\ApiUsageLog;
use App\Models\BillingWebhookEvent;
use App\Models\Incident;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use App\Services\Service;
use App\Services\System\HealthCheckService;

final class First24HourMonitoringService extends Service
{
    public function __construct(
        private readonly HealthCheckService $health,
        private readonly MonitoringService $monitoring,
        private readonly LaunchDayIncidentService $incidents,
        private readonly RollbackTriggerReviewService $rollback,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('launch_monitoring_started');

        $monitoring = $this->monitoring->review();
        $sections = [
            'health' => $this->healthReview(),
            'queue' => $this->queueReview(),
            'provider' => $this->providerReview(),
            'billing' => $this->billingReview(),
            'api' => $this->apiReview(),
            'operations' => $this->operationsReview($monitoring),
        ];
        $incidentReview = $this->incidents->review();
        $rollback = $this->rollback->review();
        $critical = [
            ...$this->issues($sections, 'critical'),
            ...($incidentReview['status'] === 'critical' ? [$this->issue('incidents', 'critical_incidents', 'Critical launch-day incidents are open.')] : []),
            ...($rollback['status'] === 'rollback-recommended' ? [$this->issue('rollback', 'rollback_recommended', 'Rollback trigger threshold has been reached.')] : []),
        ];
        $warnings = [
            ...$this->issues($sections, 'warnings'),
            ...($incidentReview['status'] === 'warning' ? [$this->issue('incidents', 'open_incidents', 'Launch-day incidents require review.')] : []),
            ...($rollback['status'] === 'monitor' ? [$this->issue('rollback', 'monitor_triggers', 'Rollback triggers require monitoring.')] : []),
        ];
        $status = $critical !== [] ? 'critical' : ($warnings !== [] ? 'warning' : 'healthy');

        $this->record('launch_monitoring_'.$status, $status === 'critical' ? OperationSeverity::Critical : OperationSeverity::Info, [
            'critical_count' => count($critical),
            'warning_count' => count($warnings),
            'rollback_status' => $rollback['status'],
        ]);

        return [
            'status' => $status,
            'critical' => $critical,
            'warnings' => $warnings,
            'incidents' => $incidentReview,
            'rollback' => $rollback,
            'sections' => $sections,
            'monitoring_review' => [
                'status' => $monitoring['status'],
                'alerts_created' => $monitoring['alerts_created'],
                'incidents_created' => $monitoring['incidents_created'],
            ],
        ];
    }

    private function healthReview(): array
    {
        $report = $this->health->report();
        $checks = [
            $this->check('health_checks', ! (bool) config('production.first_24_hours.review.health_checks_required', true) || $report['status'] === 'ok', 'Health checks are operational.', 'Health checks are degraded.', 'critical'),
            $this->check('alert_readiness', (bool) config('production.first_24_hours.review.alert_readiness', true), 'Alert readiness is documented.', 'Alert readiness needs review.', 'warning'),
            $this->check('incident_readiness', (bool) config('production.first_24_hours.review.incident_readiness', true), 'Incident readiness is documented.', 'Incident readiness needs review.', 'warning'),
        ];

        return $this->summarize($checks);
    }

    private function queueReview(): array
    {
        $latest = QueueMetric::query()->latest('measured_at')->first();
        $pending = (int) ($latest?->pending_jobs ?? 0);
        $failed = (int) ($latest?->failed_jobs ?? 0);
        $checks = [
            $this->check('queue_metrics', ! (bool) config('production.first_24_hours.review.queue_metrics_required', true) || $latest instanceof QueueMetric, 'Queue metrics are available.', 'Queue metrics are not available yet.', 'warning'),
            $this->threshold('queue_pending', $pending, 'queue_pending_warning', 'queue_pending_critical'),
            $this->threshold('queue_failed', $failed, 'queue_failed_warning', 'queue_failed_critical'),
        ];

        return $this->summarize($checks);
    }

    private function providerReview(): array
    {
        $window = $this->window();
        $failures = $this->eventCount(['provider_intake_failed'], $window);
        $webhookFailures = $this->eventCount(['webhook_rejected'], $window);
        $checks = [
            $this->check('provider_metrics', (bool) config('production.first_24_hours.review.provider_metrics_required', true), 'Provider metrics readiness is enabled.', 'Provider metrics readiness needs review.', 'warning'),
            $this->threshold('provider_failures', $failures, 'provider_failures_warning', 'provider_failures_critical'),
            $this->threshold('webhook_failures', $webhookFailures, 'webhook_failures_warning', 'webhook_failures_critical'),
        ];

        return $this->summarize($checks);
    }

    private function billingReview(): array
    {
        $failures = BillingWebhookEvent::query()
            ->where('created_at', '>=', $this->window())
            ->whereIn('status', [BillingWebhookStatus::Failed->value, BillingWebhookStatus::Rejected->value])
            ->count();
        $warning = (int) config('production.first_24_hours.thresholds.billing_failures_warning', 1);

        return $this->summarize([
            $this->check('billing_metrics', (bool) config('production.first_24_hours.review.billing_metrics_required', true), 'Billing metrics readiness is enabled.', 'Billing metrics readiness needs review.', 'warning'),
            $this->check('billing_failures', $failures < $warning, 'Billing webhook failures are within launch-day threshold.', 'Billing webhook failures require review.', 'warning'),
        ]);
    }

    private function apiReview(): array
    {
        $failures = (int) ApiUsageLog::query()
            ->where('occurred_at', '>=', $this->window())
            ->where('response_status', '>=', 400)
            ->sum('request_count');
        $warning = (int) config('production.first_24_hours.thresholds.api_failures_warning', 25);

        return $this->summarize([
            $this->check('api_metrics', (bool) config('production.first_24_hours.review.api_metrics_required', true), 'API metrics readiness is enabled.', 'API metrics readiness needs review.', 'warning'),
            $this->check('api_failures', $failures < $warning, 'API failures are within launch-day threshold.', 'API failures require review.', 'warning'),
        ]);
    }

    private function operationsReview(array $monitoring): array
    {
        $openCritical = Incident::query()
            ->where('status', '!=', IncidentStatus::Resolved->value)
            ->where('severity', IncidentSeverity::Critical->value)
            ->count();

        return $this->summarize([
            $this->check('operations_metrics', (bool) config('production.first_24_hours.review.operations_metrics_required', true), 'Operations metrics readiness is enabled.', 'Operations metrics readiness needs review.', 'warning'),
            $this->check('monitoring_aggregation', $monitoring['status'] !== 'disabled', 'Monitoring aggregation is available.', 'Monitoring aggregation is disabled.', 'warning'),
            $this->check('critical_incidents', $openCritical < (int) config('production.first_24_hours.thresholds.incident_critical', 1), 'Critical incident count is within threshold.', 'Critical incident threshold reached.', 'critical'),
        ]);
    }

    private function threshold(string $name, int $value, string $warningKey, string $criticalKey): array
    {
        $warning = (int) config("production.first_24_hours.thresholds.{$warningKey}", 1);
        $critical = (int) config("production.first_24_hours.thresholds.{$criticalKey}", PHP_INT_MAX);
        $classification = $value >= $critical ? 'critical' : ($value >= $warning ? 'warning' : 'passed');

        return [
            'name' => $name,
            'passed' => $classification === 'passed',
            'classification' => $classification,
            'message' => str($name)->replace('_', ' ')->headline().' count is '.$value.'.',
        ];
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
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'critical' => collect($checks)->where('classification', 'critical')->values()->all(),
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

    private function issue(string $category, string $name, string $message): array
    {
        return compact('category', 'name', 'message');
    }

    private function eventCount(array $types, mixed $window): int
    {
        return OperationsEvent::query()
            ->whereIn('event_type', $types)
            ->where('occurred_at', '>=', $window)
            ->count();
    }

    private function window(): mixed
    {
        return now()->subHours((int) config('production.first_24_hours.window_hours', 24));
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'first-24-hour-monitoring',
            'First 24-hour launch monitoring event recorded.',
            $metadata,
        );
    }
}
