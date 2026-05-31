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

    'enabled' => env('ABUSE_PROTECTION_ENABLED', false),
    'reporting_address' => env('ABUSE_REPORTING_ADDRESS', 'abuse@example.com'),
    'rate_limits' => [
        'enabled' => env('ABUSE_RATE_LIMITS_ENABLED', false),
        'per_minute' => env('ABUSE_RATE_LIMIT_PER_MINUTE', 60),
        'per_hour' => env('ABUSE_RATE_LIMIT_PER_HOUR', 1000),
    ],
    'cooldowns' => [
        'enabled' => env('ABUSE_COOLDOWNS_ENABLED', false),
        'seconds' => env('ABUSE_DEFAULT_COOLDOWN_SECONDS', 60),
    ],
];
