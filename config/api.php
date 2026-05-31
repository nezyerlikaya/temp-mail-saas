<?php

return [
    'enabled' => env('API_ENABLED', true),
    'key_prefix' => env('API_KEY_PREFIX', 'tm'),
    'key_bytes' => env('API_KEY_BYTES', 32),
    'default_expiration_days' => env('API_KEY_DEFAULT_EXPIRATION_DAYS'),
    'usage_logging_enabled' => env('API_USAGE_LOGGING_ENABLED', true),
    'default_limits' => [
        'per_minute' => env('API_DEFAULT_RATE_LIMIT_PER_MINUTE', 30),
    ],
    'plan_limits' => [
        'free' => [
            'api_enabled' => false,
            'api_rate_limit_per_minute' => 5,
        ],
        'member' => [
            'api_enabled' => true,
            'api_rate_limit_per_minute' => 60,
        ],
        'premium' => [
            'api_enabled' => true,
            'api_rate_limit_per_minute' => 300,
        ],
    ],
];
