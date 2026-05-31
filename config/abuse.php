<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Abuse Module Placeholder
    |--------------------------------------------------------------------------
    |
    | Reserved for future rate limits, block lists, reporting workflows, and
    | automated safeguards. No enforcement behavior is enabled in STEP01.
    |
    */

    'enabled' => env('ABUSE_PROTECTION_ENABLED', true),
    'reporting_address' => env('ABUSE_REPORTING_ADDRESS', 'abuse@example.com'),
    'hash_salt' => env('ABUSE_HASH_SALT', env('APP_KEY', 'local-abuse-salt')),
    'ip_hashing_enabled' => env('ABUSE_IP_HASHING_ENABLED', true),
    'session_hashing_enabled' => env('ABUSE_SESSION_HASHING_ENABLED', true),
    'user_signal_enabled' => env('ABUSE_USER_SIGNAL_ENABLED', true),
    'mailbox_generation_limits' => [
        'per_minute' => env('ABUSE_MAILBOX_GENERATION_PER_MINUTE', 10),
        'cooldown_seconds' => env('ABUSE_MAILBOX_GENERATION_COOLDOWN_SECONDS', 60),
    ],
    'mailbox_rotation_limits' => [
        'per_minute' => env('ABUSE_MAILBOX_ROTATION_PER_MINUTE', 10),
        'cooldown_seconds' => env('ABUSE_MAILBOX_ROTATION_COOLDOWN_SECONDS', 60),
    ],
    'polling_limits' => [
        'per_minute' => env('ABUSE_INBOX_POLLING_PER_MINUTE', 30),
        'cooldown_seconds' => env('ABUSE_INBOX_POLLING_COOLDOWN_SECONDS', 60),
    ],
    'message_detail_limits' => [
        'per_minute' => env('ABUSE_MESSAGE_DETAIL_PER_MINUTE', 60),
        'cooldown_seconds' => env('ABUSE_MESSAGE_DETAIL_COOLDOWN_SECONDS', 60),
    ],
    'login_attempt_limits' => [
        'per_minute' => env('ABUSE_LOGIN_ATTEMPT_PER_MINUTE', 20),
        'cooldown_seconds' => env('ABUSE_LOGIN_ATTEMPT_COOLDOWN_SECONDS', 60),
    ],
    'registration_attempt_limits' => [
        'per_minute' => env('ABUSE_REGISTRATION_ATTEMPT_PER_MINUTE', 10),
        'cooldown_seconds' => env('ABUSE_REGISTRATION_ATTEMPT_COOLDOWN_SECONDS', 60),
    ],
    'cooldown_seconds' => env('ABUSE_DEFAULT_COOLDOWN_SECONDS', 60),
    'progressive_penalties' => [
        'enabled' => env('ABUSE_PROGRESSIVE_PENALTIES_ENABLED', true),
        'multiplier' => env('ABUSE_PROGRESSIVE_PENALTY_MULTIPLIER', 2),
        'maximum_seconds' => env('ABUSE_PROGRESSIVE_PENALTY_MAXIMUM_SECONDS', 3600),
    ],
    'risk_score_thresholds' => [
        'throttle' => env('ABUSE_RISK_SCORE_THROTTLE', 40),
        'block' => env('ABUSE_RISK_SCORE_BLOCK', 70),
        'escalate' => env('ABUSE_RISK_SCORE_ESCALATE', 90),
    ],
    'captcha_escalation_enabled' => env('ABUSE_CAPTCHA_ESCALATION_ENABLED', false),
    'trusted_proxies_placeholder' => array_filter(array_map(
        'trim',
        explode(',', env('ABUSE_TRUSTED_PROXIES', '')),
    )),
    'rate_limits' => [
        'enabled' => env('ABUSE_RATE_LIMITS_ENABLED', true),
        'per_minute' => env('ABUSE_RATE_LIMIT_PER_MINUTE', 60),
        'per_hour' => env('ABUSE_RATE_LIMIT_PER_HOUR', 1000),
    ],
    'cooldowns' => [
        'enabled' => env('ABUSE_COOLDOWNS_ENABLED', false),
        'seconds' => env('ABUSE_DEFAULT_COOLDOWN_SECONDS', 60),
    ],
];
