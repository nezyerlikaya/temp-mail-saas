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
    'public_mailbox' => [
        'default_domain' => env('PUBLIC_MAILBOX_DOMAIN', 'example.test'),
        'allowed_domains' => array_filter(array_map(
            'trim',
            explode(',', env('PUBLIC_MAILBOX_ALLOWED_DOMAINS', env('PUBLIC_MAILBOX_DOMAIN', 'example.test'))),
        )),
    ],
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
