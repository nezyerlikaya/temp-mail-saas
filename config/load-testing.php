<?php

return [
    'thresholds' => [
        'queue_pending_warning' => env('LOAD_TEST_QUEUE_PENDING_WARNING', 100),
        'queue_pending_blocker' => env('LOAD_TEST_QUEUE_PENDING_BLOCKER', 500),
        'failed_jobs_warning' => env('LOAD_TEST_FAILED_JOBS_WARNING', 1),
        'intake_per_minute_warning' => env('LOAD_TEST_INTAKE_PER_MINUTE_WARNING', 120),
        'active_domain_minimum' => env('LOAD_TEST_ACTIVE_DOMAIN_MINIMUM', 1),
        'operations_recent_metric_minimum' => env('LOAD_TEST_OPERATIONS_RECENT_METRIC_MINIMUM', 0),
    ],

    'stress' => [
        'queue_backlog_warning' => env('STRESS_QUEUE_BACKLOG_WARNING', 250),
        'cleanup_minimum_chunk_size' => env('STRESS_CLEANUP_MINIMUM_CHUNK_SIZE', 100),
        'polling_requests_per_hour' => env('STRESS_POLLING_REQUESTS_PER_HOUR', 1000),
        'inbox_creations_per_hour' => env('STRESS_INBOX_CREATIONS_PER_HOUR', 100),
        'provider_emails_per_hour' => env('STRESS_PROVIDER_EMAILS_PER_HOUR', 500),
        'billing_events_per_hour' => env('STRESS_BILLING_EVENTS_PER_HOUR', 100),
        'operations_events_per_hour' => env('STRESS_OPERATIONS_EVENTS_PER_HOUR', 1000),
    ],

    'polling' => [
        'max_poll_limit' => env('LOAD_TEST_MAX_INBOX_POLL_LIMIT', 50),
        'recommended_interval_ms' => env('LOAD_TEST_RECOMMENDED_POLLING_INTERVAL_MS', 15000),
    ],

    'queue' => [
        'required_queues' => array_filter(array_map(
            'trim',
            explode(',', env('LOAD_TEST_REQUIRED_QUEUES', 'inbound-mail')),
        )),
        'queue_first_required' => true,
    ],

    'providers' => [
        'required_duplicate_protection' => true,
        'required_replay_protection' => true,
        'review_providers' => array_filter(array_map(
            'trim',
            explode(',', env('LOAD_TEST_REVIEW_PROVIDERS', 'mailgun,postmark,ses')),
        )),
    ],

    'scenarios' => [
        'inbox_creation' => [
            'label' => '100 inbox creations/hour',
            'assumption' => 100,
            'unit' => 'hour',
        ],
        'inbox_polling' => [
            'label' => '1000 inbox polls/hour',
            'assumption' => 1000,
            'unit' => 'hour',
        ],
        'provider_intake' => [
            'label' => '500 inbound emails/hour',
            'assumption' => 500,
            'unit' => 'hour',
        ],
        'queue_backlog' => [
            'label' => 'Queue backlog scenario',
            'assumption' => 250,
            'unit' => 'pending_jobs',
        ],
        'provider_failure' => [
            'label' => 'Provider failure scenario',
            'assumption' => 5,
            'unit' => 'failures/hour',
        ],
    ],
];
