<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Module Placeholder
    |--------------------------------------------------------------------------
    |
    | Reserved for domain inventory, verification, routing, and DNS-related
    | settings in future steps. No domain business behavior is enabled here.
    |
    */

    'default_status' => 'inactive',
    'verification' => [
        'enabled' => false,
    ],
    'rotation' => [
        'enabled' => env('DOMAIN_ROTATION_ENABLED', false),
        'strategy' => env('DOMAIN_ROTATION_STRATEGY', 'manual'),
    ],
    'health' => [
        'enabled' => env('DOMAIN_HEALTH_CHECKS_ENABLED', false),
        'failure_threshold' => env('DOMAIN_HEALTH_FAILURE_THRESHOLD', 3),
    ],
];
