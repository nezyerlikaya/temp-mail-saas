<?php

return [
    'enabled' => env('OPERATIONS_ENABLED', true),
    'metrics' => [
        'schedule_enabled' => env('OPERATIONS_METRICS_SCHEDULE_ENABLED', false),
        'schedule_frequency' => env('OPERATIONS_METRICS_SCHEDULE_FREQUENCY', 'hourly'),
    ],
    'thresholds' => [
        'queue_pending_warning' => env('OPERATIONS_QUEUE_PENDING_WARNING', 100),
        'queue_failed_warning' => env('OPERATIONS_QUEUE_FAILED_WARNING', 1),
        'domain_warning_score' => env('OPERATIONS_DOMAIN_WARNING_SCORE', 70),
        'domain_critical_score' => env('OPERATIONS_DOMAIN_CRITICAL_SCORE', 40),
    ],
    'event_retention_days' => env('OPERATIONS_EVENT_RETENTION_DAYS', 30),
    'queue_names' => array_filter(array_map(
        'trim',
        explode(',', env('OPERATIONS_QUEUE_NAMES', 'default')),
    )),
];
