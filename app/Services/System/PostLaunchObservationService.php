<?php

namespace App\Services\System;

use App\Services\Service;

final class PostLaunchObservationService extends Service
{
    public function plan(): array
    {
        return [
            'window_days' => (int) config('production.public_launch.observation.window_days', 7),
            'daily_checkpoints' => (int) config('production.public_launch.observation.daily_checkpoints', 3),
            'monitoring_priorities' => ['health', 'queue', 'providers', 'domains', 'inbox', 'billing', 'api', 'abuse'],
            'incident_review_priorities' => ['critical_incidents', 'provider_failures', 'queue_backlog', 'inbox_visibility', 'abuse_spikes'],
            'rollback_triggers' => ['critical_incident_open', 'provider_failure_spike', 'queue_backlog_critical', 'inbox_visibility_failure', 'security_regression'],
            'operational_checkpoints' => ['review_health', 'review_incidents', 'review_rollback_triggers', 'review_support_queue'],
        ];
    }
}
