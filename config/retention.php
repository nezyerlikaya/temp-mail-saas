<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Retention Placeholder
    |--------------------------------------------------------------------------
    |
    | Reserved for future mailbox, email, abuse, and audit retention policies.
    | Values are intentionally conservative and inactive until implemented.
    |
    */

    'enabled' => env('RETENTION_ENABLED', true),
    'default_mailbox_ttl_minutes' => env('TEMPMAIL_DEFAULT_MAILBOX_TTL_MINUTES', 60),
    'default_ttl_minutes' => env('TEMPMAIL_DEFAULT_TTL_MINUTES', 60),
    'default_email_retention_tier' => env('EMAIL_DEFAULT_RETENTION_TIER', 'standard'),
    'cleanup_chunk_size' => env('RETENTION_CLEANUP_CHUNK_SIZE', 100),
    'cleanup_dry_run_default' => env('RETENTION_CLEANUP_DRY_RUN_DEFAULT', false),
    'hard_delete_enabled' => env('RETENTION_HARD_DELETE_ENABLED', false),
    'expired_message_action' => env('EMAIL_EXPIRED_MESSAGE_ACTION', 'mark'),
    'intake_retention_minutes' => env('INBOUND_INTAKE_RETENTION_MINUTES', 10080),
    'attachment_metadata_behavior' => env('ATTACHMENT_METADATA_RETENTION_BEHAVIOR', 'retain_until_message_delete'),
    'cleanup_log_enabled' => env('RETENTION_CLEANUP_LOG_ENABLED', true),
    'schedule' => [
        'enabled' => env('RETENTION_SCHEDULE_ENABLED', false),
        'frequency' => env('RETENTION_SCHEDULE_FREQUENCY', 'hourly'),
    ],
    'defaults' => [
        'mailbox_ttl_minutes' => env('TEMPMAIL_DEFAULT_MAILBOX_TTL_MINUTES', 60),
        'email_ttl_minutes' => env('TEMPMAIL_DEFAULT_EMAIL_TTL_MINUTES', 60),
        'abuse_log_days' => env('ABUSE_LOG_RETENTION_DAYS', 30),
    ],
    'email' => [
        'default_tier' => env('EMAIL_DEFAULT_RETENTION_TIER', 'standard'),
        'tiers' => [
            'short' => env('EMAIL_RETENTION_SHORT_MINUTES', 60),
            'standard' => env('EMAIL_RETENTION_STANDARD_MINUTES', 1440),
            'premium' => env('EMAIL_RETENTION_PREMIUM_MINUTES', 10080),
        ],
    ],
];
