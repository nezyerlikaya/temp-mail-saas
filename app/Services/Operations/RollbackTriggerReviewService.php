<?php

namespace App\Services\Operations;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Incident;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use App\Services\Service;

final class RollbackTriggerReviewService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $window = now()->subHours((int) config('production.first_24_hours.window_hours', 24));
        $latestQueue = QueueMetric::query()->latest('measured_at')->first();
        $criticalIncidents = Incident::query()
            ->where('status', '!=', IncidentStatus::Resolved->value)
            ->where('severity', IncidentSeverity::Critical->value)
            ->count();
        $providerFailures = $this->eventCount(['provider_intake_failed', 'webhook_rejected'], $window);
        $inboxFailures = $this->eventCount(['inbox_polling_failed', 'first_live_mail_review_blocked'], $window);
        $checks = [
            $this->check('critical_incidents', $criticalIncidents, (int) config('production.first_24_hours.rollback.critical_incident_threshold', 1)),
            $this->check('queue_pending', (int) ($latestQueue?->pending_jobs ?? 0), (int) config('production.first_24_hours.rollback.queue_pending_threshold', 500)),
            $this->check('queue_failed', (int) ($latestQueue?->failed_jobs ?? 0), (int) config('production.first_24_hours.rollback.queue_failed_threshold', 10)),
            $this->check('provider_failures', $providerFailures, (int) config('production.first_24_hours.rollback.provider_failure_threshold', 20)),
            $this->check('inbox_failures', $inboxFailures, (int) config('production.first_24_hours.rollback.inbox_failure_threshold', 30)),
        ];
        $rollback = collect($checks)->where('classification', 'rollback')->values()->all();
        $monitor = collect($checks)->where('classification', 'monitor')->values()->all();
        $status = $rollback !== [] ? 'rollback-recommended' : ($monitor !== [] ? 'monitor' : 'safe');

        $this->operations->log(
            OperationCategory::System,
            'launch_monitoring_rollback_reviewed',
            $status === 'rollback-recommended' ? OperationSeverity::Critical : OperationSeverity::Info,
            OperationStatus::Detected,
            'first-24-hour-monitoring',
            'Launch rollback trigger readiness reviewed.',
            [
                'status' => $status,
                'rollback_count' => count($rollback),
                'monitor_count' => count($monitor),
            ],
        );

        return [
            'status' => $status,
            'rollback_triggers' => $rollback,
            'monitor_triggers' => $monitor,
            'checks' => $checks,
        ];
    }

    private function check(string $name, int $value, int $threshold): array
    {
        $classification = $value >= $threshold ? 'rollback' : ($value > 0 ? 'monitor' : 'safe');

        return [
            'name' => $name,
            'value' => $value,
            'threshold' => $threshold,
            'classification' => $classification,
            'message' => str($name)->replace('_', ' ')->headline().' value is '.$value.'.',
        ];
    }

    private function eventCount(array $types, mixed $window): int
    {
        return OperationsEvent::query()
            ->whereIn('event_type', $types)
            ->where('occurred_at', '>=', $window)
            ->count();
    }
}
