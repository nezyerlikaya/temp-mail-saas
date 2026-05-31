<?php

namespace App\Services\System;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Mail\LoadReadinessService;
use App\Services\Operations\MonitoringService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class StressReadinessService extends Service
{
    public function __construct(
        private readonly LoadReadinessService $load,
        private readonly LoadScenarioService $scenarios,
        private readonly MonitoringService $monitoring,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $load = $this->load->report();
        $scenarioSummary = $this->scenarios->summary();
        $monitoring = $this->monitoring->summary();
        $checks = [
            $this->check('queue_throughput_assumption', ($load['queue']['pending_jobs'] ?? 0) < (int) config('load-testing.stress.queue_backlog_warning', 250), 'Queue backlog is below stress warning assumption.', 'Queue backlog is near the stress warning assumption.', 'warning'),
            $this->check('cleanup_throughput_assumption', (int) config('retention.cleanup_chunk_size', 100) >= (int) config('load-testing.stress.cleanup_minimum_chunk_size', 100), 'Cleanup chunk size matches stress assumption.', 'Cleanup chunk size is below stress assumption.', 'warning'),
            $this->check('polling_assumption', (int) config('performance.thresholds.inbox_poll_limit', 50) <= (int) config('load-testing.polling.max_poll_limit', 50), 'Inbox polling retrieval limit is bounded.', 'Inbox polling retrieval limit is above the stress assumption.', 'warning'),
            $this->check('provider_intake_assumption', (int) config('load-testing.stress.provider_emails_per_hour', 500) > 0, 'Provider intake assumption is documented.', 'Provider intake assumption is missing.', 'warning'),
            $this->check('billing_assumption', (int) config('load-testing.stress.billing_events_per_hour', 100) > 0, 'Billing load assumption is documented.', 'Billing load assumption is missing.', 'warning'),
            $this->check('operations_assumption', (int) ($monitoring['active_alerts'] ?? 0) < (int) config('monitoring.thresholds.queue_pending_critical', 500), 'Operations alert volume is acceptable for stress review.', 'Operations alert volume needs review.', 'warning'),
            $this->check('scenario_framework', $scenarioSummary['scenario_count'] > 0, 'Load scenarios are documented.', 'Load scenarios are missing.', 'warning'),
        ];

        $warnings = collect($checks)->where('status', 'warning')->values()->all();
        $status = $warnings === [] ? 'ready' : 'warning';

        $this->record($warnings === [] ? 'stress_review_completed' : 'stress_review_warning', $status);

        return [
            'status' => $status,
            'checks' => $checks,
            'passed' => collect($checks)->where('status', 'passed')->values()->all(),
            'warnings' => $warnings,
            'blockers' => [],
            'recommendations' => $this->recommendations($checks),
            'scenarios' => $scenarioSummary,
        ];
    }

    private function recommendations(array $checks): array
    {
        $recommendations = collect($checks)
            ->where('status', 'warning')
            ->map(fn (array $check): string => $check['message'])
            ->values()
            ->all();

        return array_values(array_unique([
            ...$recommendations,
            'Review queue workers, cleanup cadence, inbox polling limits, provider intake, billing webhooks, and operations dashboards before external load execution.',
        ]));
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $failedStatus = 'warning'): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'status' => $passed ? 'passed' : $failedStatus,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function record(string $eventType, string $status): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $status === 'warning' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'stress-readiness',
            'Stress readiness review event recorded.',
            ['status' => $status],
        );
    }
}
