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

    'enabled' => env('RETENTION_ENABLED', false),
    'default_mailbox_ttl_minutes' => env('TEMPMAIL_DEFAULT_MAILBOX_TTL_MINUTES', 60),
    'default_ttl_minutes' => env('TEMPMAIL_DEFAULT_TTL_MINUTES', 60),
    'cleanup_chunk_size' => env('RETENTION_CLEANUP_CHUNK_SIZE', 100),
    'expired_message_action' => env('EMAIL_EXPIRED_MESSAGE_ACTION', 'mark'),
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
