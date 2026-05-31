<?php

return [
    'enabled' => env('BILLING_ENABLED', true),
    'default_provider' => env('BILLING_DEFAULT_PROVIDER', 'local'),
    'supported_providers' => ['local', 'stripe', 'paddle', 'lemon_squeezy'],
    'webhook_tolerance_seconds' => env('BILLING_WEBHOOK_TOLERANCE_SECONDS', 300),
    'providers' => [
        'local' => [
            'webhook_secret' => env('LOCAL_BILLING_WEBHOOK_SECRET', 'local-testing-secret'),
            'allow_unsigned_in_testing' => env('LOCAL_BILLING_ALLOW_UNSIGNED_TESTING', false),
        ],
        'stripe' => ['webhook_secret' => env('STRIPE_WEBHOOK_SECRET')],
        'paddle' => ['webhook_secret' => env('PADDLE_WEBHOOK_SECRET')],
        'lemon_squeezy' => ['webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET')],
    ],
    'invoice_metadata' => [
        'store_hosted_invoice_url' => true,
        'store_pdf_url' => true,
    ],
    'provider_plan_map' => [
        'local_free' => 'free',
        'local_member' => 'member',
        'local_premium' => 'premium',
    ],
];
