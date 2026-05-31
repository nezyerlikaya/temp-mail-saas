<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Future modules should register flags here before exposing user-facing
    | behavior. Flags default to false to keep rollout non-breaking.
    |
    */

    'auth' => [
        'enabled' => env('FEATURE_AUTH_ENABLED', false),
    ],

    'admin' => [
        'enabled' => env('FEATURE_ADMIN_ENABLED', false),
    ],

    'api' => [
        'enabled' => env('FEATURE_API_ENABLED', false),
    ],

    'seo' => [
        'enabled' => env('FEATURE_SEO_ENABLED', true),
    ],

    'localization' => [
        'enabled' => env('FEATURE_LOCALIZATION_ENABLED', true),
    ],

    'billing' => [
        'enabled' => env('FEATURE_BILLING_ENABLED', false),
    ],

    'inbound' => [
        'enabled' => env('FEATURE_INBOUND_ENABLED', false),
    ],

    'custom_domains' => [
        'enabled' => env('FEATURE_CUSTOM_DOMAINS_ENABLED', false),
    ],

    'user_accounts' => [
        'enabled' => env('FEATURE_USER_ACCOUNTS_ENABLED', false),
    ],
];
