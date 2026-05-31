<?php

return [
    'enabled' => env('MONITORING_ENABLED', true),

    'thresholds' => [
        'queue_pending_warning' => env('MONITORING_QUEUE_PENDING_WARNING', 100),
        'queue_pending_critical' => env('MONITORING_QUEUE_PENDING_CRITICAL', 500),
        'queue_failed_warning' => env('MONITORING_QUEUE_FAILED_WARNING', 1),
        'queue_failed_critical' => env('MONITORING_QUEUE_FAILED_CRITICAL', 10),
        'provider_failure_warning' => env('MONITORING_PROVIDER_FAILURE_WARNING', 5),
        'provider_rejection_warning' => env('MONITORING_PROVIDER_REJECTION_WARNING', 10),
        'provider_throughput_warning' => env('MONITORING_PROVIDER_THROUGHPUT_WARNING', 250),
        'api_usage_spike_warning' => env('MONITORING_API_USAGE_SPIKE_WARNING', 1000),
        'api_failure_warning' => env('MONITORING_API_FAILURE_WARNING', 25),
        'billing_webhook_failure_warning' => env('MONITORING_BILLING_WEBHOOK_FAILURE_WARNING', 1),
    ],

    'incidents' => [
        'create_for_critical_alerts' => env('MONITORING_CREATE_INCIDENTS_FOR_CRITICAL_ALERTS', true),
        'metadata_limit' => env('MONITORING_INCIDENT_METADATA_LIMIT', 20),
    ],

    'alerts' => [
        'deduplicate_active' => env('MONITORING_DEDUPLICATE_ACTIVE_ALERTS', true),
        'message_length' => env('MONITORING_ALERT_MESSAGE_LENGTH', 255),
    ],

    'intervals' => [
        'review_window_minutes' => env('MONITORING_REVIEW_WINDOW_MINUTES', 60),
        'uptime_review_minutes' => env('MONITORING_UPTIME_REVIEW_MINUTES', 5),
    ],
];
