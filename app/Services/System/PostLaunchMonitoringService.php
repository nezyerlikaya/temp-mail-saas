<?php

namespace App\Services\System;

use App\Services\Operations\MonitoringService;
use App\Services\Service;

final class PostLaunchMonitoringService extends Service
{
    public function __construct(private readonly MonitoringService $monitoring) {}

    public function plan(): array
    {
        $summary = $this->monitoring->summary();
        $signals = [
            'health_status',
            'queue_backlog',
            'provider_failures',
            'webhook_failures',
            'inbox_polling_errors',
            'abuse_spikes',
            'billing_webhook_failures',
            'api_errors',
            'incident_count',
        ];
        $triggers = [
            'critical_incident_open' => (bool) config('production.v1_launch.rollback_triggers.critical_incident_open', true),
            'queue_backlog_critical' => (bool) config('production.v1_launch.rollback_triggers.queue_backlog_critical', true),
            'provider_failure_spike' => (bool) config('production.v1_launch.rollback_triggers.provider_failure_spike', true),
            'mail_reception_failure' => (bool) config('production.v1_launch.rollback_triggers.mail_reception_failure', true),
            'security_regression' => (bool) config('production.v1_launch.rollback_triggers.security_regression', true),
        ];

        return [
            'status' => ((int) ($summary['critical_incidents'] ?? 0)) === 0 ? 'ready' : 'blocked',
            'window_hours' => 24,
            'critical_signals' => $signals,
            'incident_triggers' => array_keys(array_filter($triggers)),
            'rollback_triggers' => array_keys(array_filter($triggers)),
            'thresholds' => config('production.v1_launch.monitoring_24h', []),
            'operator_guidance' => [
                'Watch health, queue, provider, inbox, abuse, billing, API, and incident signals continuously during the first 24 hours.',
                'Pause launch expansion when any rollback trigger is met.',
                'Keep reports free of secrets, raw payloads, and credentials.',
            ],
        ];
    }
}
