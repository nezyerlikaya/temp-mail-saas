<?php

return [
    'default_strategy' => env('DOMAIN_POOL_DEFAULT_STRATEGY', 'health_based'),
    'health_thresholds' => [
        'healthy' => env('DOMAIN_POOL_HEALTHY_SCORE', 80),
        'warning' => env('DOMAIN_POOL_WARNING_SCORE', 50),
    ],
    'assignment' => [
        'record_history' => env('DOMAIN_POOL_RECORD_ASSIGNMENT_HISTORY', true),
    ],
    'fallback_domains' => array_filter(array_map(
        'trim',
        explode(',', env('DOMAIN_POOL_FALLBACK_DOMAINS', env('PUBLIC_MAILBOX_DOMAIN', 'example.test'))),
    )),
    'tier_mapping' => [
        'free' => ['free'],
        'member' => ['free', 'member'],
        'premium' => ['free', 'member', 'premium'],
        'enterprise' => ['free', 'member', 'premium', 'enterprise'],
    ],
];
