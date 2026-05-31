<?php

namespace App\Services\Operations;

use App\Enums\BillingWebhookStatus;
use App\Enums\IncidentSeverity;
use App\Enums\MonitoringAlertStatus;
use App\Models\ApiUsageLog;
use App\Models\BillingWebhookEvent;
use App\Models\MonitoringAlert;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MonitoringService extends Service
{
    public function __construct(private readonly IncidentService $incidents)
    {
    }

    public function review(): array
    {
        if (! (bool) config('monitoring.enabled', true)) {
            return [
                'status' => 'disabled',
                'alerts_created' => 0,
                'incidents_created' => 0,
                'sections' => [],
            ];
        }

        $beforeAlerts = MonitoringAlert::query()->count();
        $beforeIncidents = \App\Models\Incident::query()->count();
        $sections = [
            'queue' => $this->reviewQueues(),
            'providers' => $this->reviewProviders(),
            'api' => $this->reviewApi(),
            'billing' => $this->reviewBilling(),
        ];

        return [
            'status' => 'reviewed',
            'alerts_created' => MonitoringAlert::query()->count() - $beforeAlerts,
            'incidents_created' => \App\Models\Incident::query()->count() - $beforeIncidents,
            'sections' => $sections,
        ];
    }

    public function createAlert(
        string $source,
        string $alertType,
        IncidentSeverity|string $severity,
        ?string $message = null,
    ): MonitoringAlert {
        $severity = $this->severity($severity);

        if ((bool) config('monitoring.alerts.deduplicate_active', true)) {
            $existing = MonitoringAlert::query()
                ->where('source', $source)
                ->where('alert_type', $alertType)
                ->where('status', MonitoringAlertStatus::Active)
                ->first();

            if ($existing instanceof MonitoringAlert) {
                return $existing;
            }
        }

        $alert = MonitoringAlert::query()->create([
            'uuid' => (string) Str::uuid(),
            'source' => Str::limit($source, 64, ''),
            'alert_type' => Str::limit($alertType, 64, ''),
            'severity' => $severity->value,
            'status' => MonitoringAlertStatus::Active->value,
            'message' => $message !== null ? Str::limit($message, (int) config('monitoring.alerts.message_length', 255), '') : null,
            'triggered_at' => now(),
        ]);

        if ($severity === IncidentSeverity::Critical && (bool) config('monitoring.incidents.create_for_critical_alerts', true)) {
            $this->incidents->create(
                category: $source,
                severity: $severity,
                title: Str::headline($alertType),
                summary: $alert->message,
                metadata: [
                    'alert_uuid' => $alert->uuid,
                    'source' => $source,
                    'alert_type' => $alertType,
                ],
            );
        }

        return $alert;
    }

    public function reviewQueues(): array
    {
        $pendingWarning = (int) config('monitoring.thresholds.queue_pending_warning', 100);
        $pendingCritical = (int) config('monitoring.thresholds.queue_pending_critical', 500);
        $failedWarning = (int) config('monitoring.thresholds.queue_failed_warning', 1);
        $failedCritical = (int) config('monitoring.thresholds.queue_failed_critical', 10);
        $latest = QueueMetric::query()->latest('measured_at')->first();

        if (! $latest instanceof QueueMetric) {
            return ['status' => 'empty', 'alerts' => 0];
        }

        $alerts = 0;

        if ($latest->pending_jobs >= $pendingWarning) {
            $this->createAlert(
                'queue',
                'queue_lag',
                $latest->pending_jobs >= $pendingCritical ? IncidentSeverity::Critical : IncidentSeverity::Medium,
                "Queue {$latest->queue_name} has {$latest->pending_jobs} pending jobs.",
            );
            $alerts++;
        }

        if ($latest->failed_jobs >= $failedWarning) {
            $this->createAlert(
                'queue',
                'failed_job_spike',
                $latest->failed_jobs >= $failedCritical ? IncidentSeverity::Critical : IncidentSeverity::High,
                "Queue {$latest->queue_name} has {$latest->failed_jobs} failed jobs.",
            );
            $alerts++;
        }

        return ['status' => $alerts > 0 ? 'alerts' : 'healthy', 'alerts' => $alerts];
    }

    public function reviewProviders(): array
    {
        $window = now()->subMinutes((int) config('monitoring.intervals.review_window_minutes', 60));
        $failures = $this->providerEventCount('provider_intake_failed', $window);
        $rejections = $this->providerEventCount('provider_intake_rejected', $window) + $this->providerEventCount('webhook_rejected', $window);
        $throughput = $this->providerEventCount('provider_intake_received', $window) + $this->providerEventCount('webhook_received', $window);
        $alerts = 0;

        if ($failures >= (int) config('monitoring.thresholds.provider_failure_warning', 5)) {
            $this->createAlert('provider', 'provider_failures', IncidentSeverity::High, "Provider failures in review window: {$failures}.");
            $alerts++;
        }

        if ($rejections >= (int) config('monitoring.thresholds.provider_rejection_warning', 10)) {
            $this->createAlert('provider', 'provider_rejections', IncidentSeverity::Medium, "Provider rejections in review window: {$rejections}.");
            $alerts++;
        }

        if ($throughput >= (int) config('monitoring.thresholds.provider_throughput_warning', 250)) {
            $this->createAlert('provider', 'provider_throughput', IncidentSeverity::Medium, "Provider throughput in review window: {$throughput}.");
            $alerts++;
        }

        return compact('failures', 'rejections', 'throughput', 'alerts');
    }

    public function reviewApi(): array
    {
        $window = now()->subMinutes((int) config('monitoring.intervals.review_window_minutes', 60));
        $usage = (int) ApiUsageLog::query()->where('occurred_at', '>=', $window)->sum('request_count');
        $failures = (int) ApiUsageLog::query()
            ->where('occurred_at', '>=', $window)
            ->where('response_status', '>=', 400)
            ->sum('request_count');
        $alerts = 0;

        if ($usage >= (int) config('monitoring.thresholds.api_usage_spike_warning', 1000)) {
            $this->createAlert('api', 'api_usage_spike', IncidentSeverity::Medium, "API usage in review window: {$usage}.");
            $alerts++;
        }

        if ($failures >= (int) config('monitoring.thresholds.api_failure_warning', 25)) {
            $this->createAlert('api', 'api_failures', IncidentSeverity::High, "API failures in review window: {$failures}.");
            $alerts++;
        }

        return compact('usage', 'failures', 'alerts');
    }

    public function reviewBilling(): array
    {
        $window = now()->subMinutes((int) config('monitoring.intervals.review_window_minutes', 60));
        $failures = BillingWebhookEvent::query()
            ->where('created_at', '>=', $window)
            ->whereIn('status', [BillingWebhookStatus::Failed->value, BillingWebhookStatus::Rejected->value])
            ->count();
        $alerts = 0;

        if ($failures >= (int) config('monitoring.thresholds.billing_webhook_failure_warning', 1)) {
            $this->createAlert('billing', 'billing_webhook_failures', IncidentSeverity::High, "Billing webhook failures in review window: {$failures}.");
            $alerts++;
        }

        return compact('failures', 'alerts');
    }

    public function summary(): array
    {
        return [
            'active_alerts' => MonitoringAlert::query()->where('status', MonitoringAlertStatus::Active)->count(),
            'acknowledged_alerts' => MonitoringAlert::query()->where('status', MonitoringAlertStatus::Acknowledged)->count(),
            'resolved_alerts' => MonitoringAlert::query()->where('status', MonitoringAlertStatus::Resolved)->count(),
            'open_incidents' => \App\Models\Incident::query()->where('status', \App\Enums\IncidentStatus::Open)->count(),
            'critical_incidents' => \App\Models\Incident::query()
                ->where('status', '!=', \App\Enums\IncidentStatus::Resolved->value)
                ->where('severity', IncidentSeverity::Critical->value)
                ->count(),
        ];
    }

    private function providerEventCount(string $eventType, $window): int
    {
        return OperationsEvent::query()
            ->where('source', 'inbound-provider')
            ->where('event_type', $eventType)
            ->where('occurred_at', '>=', $window)
            ->count();
    }

    private function severity(IncidentSeverity|string $severity): IncidentSeverity
    {
        return $severity instanceof IncidentSeverity
            ? $severity
            : (IncidentSeverity::tryFrom($severity) ?? IncidentSeverity::Medium);
    }
}
