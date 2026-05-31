<?php

return [
    'readiness' => [
        'https_required' => env('PRODUCTION_HTTPS_REQUIRED', true),
        'allowed_queue_drivers' => array_filter(array_map(
            'trim',
            explode(',', env('PRODUCTION_ALLOWED_QUEUE_DRIVERS', 'database,redis,sqs,sync')),
        )),
        'allow_log_mailer_in_production' => env('PRODUCTION_ALLOW_LOG_MAILER', false),
    ],
    'health' => [
        'logging_enabled' => env('SYSTEM_HEALTH_LOGGING_ENABLED', true),
        'schedule_enabled' => env('SYSTEM_HEALTH_SCHEDULE_ENABLED', false),
        'schedule_frequency' => env('SYSTEM_HEALTH_SCHEDULE_FREQUENCY', 'hourly'),
    ],
    'backup' => [
        'destination_disk' => env('BACKUP_DESTINATION_DISK', 'local'),
        'paths' => [
            storage_path('app'),
            database_path(),
        ],
    ],
    'error_tracking' => [
        'enabled' => env('ERROR_TRACKING_ENABLED', true),
        'provider' => env('ERROR_TRACKING_PROVIDER', 'log'),
    ],
];
