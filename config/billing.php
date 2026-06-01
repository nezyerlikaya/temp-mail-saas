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
    'revenue_readiness' => [
        'provider' => env('REVENUE_READINESS_PROVIDER', 'local'),
        'require_billing_enabled' => env('REVENUE_REQUIRE_BILLING_ENABLED', true),
        'require_webhook_secret' => env('REVENUE_REQUIRE_WEBHOOK_SECRET', true),
        'require_plan_map' => env('REVENUE_REQUIRE_PLAN_MAP', true),
        'require_no_card_storage' => env('REVENUE_REQUIRE_NO_CARD_STORAGE', true),
        'customer_lifecycle' => [
            'customer_creation' => env('REVENUE_CUSTOMER_CREATION_READY', true),
            'subscription_assignment' => env('REVENUE_SUBSCRIPTION_ASSIGNMENT_READY', true),
            'plan_transition' => env('REVENUE_PLAN_TRANSITION_READY', true),
            'cancellation' => env('REVENUE_CANCELLATION_READY', true),
            'renewal' => env('REVENUE_RENEWAL_READY', true),
        ],
        'subscription_operations' => [
            'activation' => env('REVENUE_SUBSCRIPTION_ACTIVATION_READY', true),
            'downgrade' => env('REVENUE_SUBSCRIPTION_DOWNGRADE_READY', true),
            'upgrade' => env('REVENUE_SUBSCRIPTION_UPGRADE_READY', true),
            'cancellation' => env('REVENUE_SUBSCRIPTION_CANCELLATION_READY', true),
            'invoice_review' => env('REVENUE_INVOICE_REVIEW_READY', true),
        ],
        'incidents' => [
            'webhook_failure' => env('REVENUE_WEBHOOK_FAILURE_READY', true),
            'invoice_failure' => env('REVENUE_INVOICE_FAILURE_READY', true),
            'subscription_mismatch' => env('REVENUE_SUBSCRIPTION_MISMATCH_READY', true),
            'rollback' => env('REVENUE_ROLLBACK_READY', true),
        ],
        'certification' => [
            'billing' => env('REVENUE_CERTIFY_BILLING', true),
            'subscription' => env('REVENUE_CERTIFY_SUBSCRIPTION', true),
            'customer_lifecycle' => env('REVENUE_CERTIFY_CUSTOMER_LIFECYCLE', true),
            'payment_incidents' => env('REVENUE_CERTIFY_PAYMENT_INCIDENTS', true),
        ],
    ],
];
