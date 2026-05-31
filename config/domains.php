<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Module Placeholder
    |--------------------------------------------------------------------------
    |
    | Domain inventory, routing, and configuration-only onboarding readiness.
    | DNS records and registrar credentials are never stored here.
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
    'onboarding' => [
        'states' => ['draft', 'validating', 'ready', 'active', 'suspended'],
        'require_active_for_assignment' => env('DOMAIN_ONBOARDING_REQUIRE_ACTIVE_FOR_ASSIGNMENT', true),
        'dns_readiness' => [
            'mx' => env('DOMAIN_ONBOARDING_MX_READY', false),
            'spf' => env('DOMAIN_ONBOARDING_SPF_READY', false),
            'dkim' => env('DOMAIN_ONBOARDING_DKIM_READY', false),
            'dmarc' => env('DOMAIN_ONBOARDING_DMARC_READY', false),
            'provider_mapping' => env('DOMAIN_ONBOARDING_PROVIDER_MAPPING_READY', false),
        ],
        'provider_mapping' => [
            'default_provider' => env('DOMAIN_ONBOARDING_DEFAULT_PROVIDER', 'local'),
            'compatible_states' => ['ready', 'active'],
        ],
        'safety' => [
            'minimum_health_score' => env('DOMAIN_ONBOARDING_MINIMUM_HEALTH_SCORE', 80),
            'warn_on_test_domain' => env('DOMAIN_ONBOARDING_WARN_ON_TEST_DOMAIN', true),
        ],
    ],
];
