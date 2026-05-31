<?php

return [
    'cache' => [
        'enabled' => env('PERFORMANCE_CACHE_ENABLED', true),

        'ttl' => [
            'health_summary' => env('PERFORMANCE_CACHE_HEALTH_TTL', 60),
            'readiness_summary' => env('PERFORMANCE_CACHE_READINESS_TTL', 60),
            'localization_progress' => env('PERFORMANCE_CACHE_LOCALIZATION_TTL', 300),
            'domain_health_summary' => env('PERFORMANCE_CACHE_DOMAIN_HEALTH_TTL', 120),
            'operations_dashboard' => env('PERFORMANCE_CACHE_OPERATIONS_TTL', 60),
        ],

        'prefix' => env('PERFORMANCE_CACHE_PREFIX', 'tempmail:performance'),
    ],

    'thresholds' => [
        'slow_query_ms' => env('PERFORMANCE_SLOW_QUERY_MS', 250),
        'admin_page_query_warning' => env('PERFORMANCE_ADMIN_QUERY_WARNING', 40),
        'inbox_poll_limit' => env('PERFORMANCE_INBOX_POLL_LIMIT', 50),
        'domain_pool_min_health' => env('PERFORMANCE_DOMAIN_POOL_MIN_HEALTH', 1),
        'queue_pending_warning' => env('PERFORMANCE_QUEUE_PENDING_WARNING', 100),
    ],

    'aggregation' => [
        'recent_audit_limit' => env('PERFORMANCE_RECENT_AUDIT_LIMIT', 10),
        'domain_health_recent_limit' => env('PERFORMANCE_DOMAIN_HEALTH_RECENT_LIMIT', 5),
        'operations_window_minutes' => env('PERFORMANCE_OPERATIONS_WINDOW_MINUTES', 60),
    ],
];
