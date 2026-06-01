<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Product Analytics Readiness
    |--------------------------------------------------------------------------
    |
    | Privacy-conscious product analytics foundations. These options prepare
    | conversion, journey, retention, and reporting reviews without external
    | analytics providers, fingerprinting, or personal data profiling.
    |
    */

    'enabled' => env('ANALYTICS_READINESS_ENABLED', true),

    'readiness' => [
        'events_ready' => env('ANALYTICS_EVENTS_READY', true),
        'metrics_ready' => env('ANALYTICS_METRICS_READY', true),
        'reporting_ready' => env('ANALYTICS_REPORTING_READY', true),
        'privacy_ready' => env('ANALYTICS_PRIVACY_READY', true),
    ],

    'conversion' => [
        'landing_visit' => env('ANALYTICS_CONVERSION_LANDING_READY', true),
        'mailbox_creation' => env('ANALYTICS_CONVERSION_MAILBOX_READY', true),
        'inbox_activation' => env('ANALYTICS_CONVERSION_INBOX_READY', true),
        'account_registration' => env('ANALYTICS_CONVERSION_REGISTRATION_READY', true),
        'premium_conversion' => env('ANALYTICS_CONVERSION_PREMIUM_READY', true),
    ],

    'journeys' => [
        'onboarding' => env('ANALYTICS_JOURNEY_ONBOARDING_READY', true),
        'inbox' => env('ANALYTICS_JOURNEY_INBOX_READY', true),
        'premium' => env('ANALYTICS_JOURNEY_PREMIUM_READY', true),
        'support' => env('ANALYTICS_JOURNEY_SUPPORT_READY', true),
    ],

    'retention' => [
        'revisit' => env('ANALYTICS_RETENTION_REVISIT_READY', true),
        'account_retention' => env('ANALYTICS_RETENTION_ACCOUNT_READY', true),
        'premium_retention' => env('ANALYTICS_RETENTION_PREMIUM_READY', true),
        'lifecycle' => env('ANALYTICS_RETENTION_LIFECYCLE_READY', true),
    ],

    'privacy' => [
        'allow_personal_profiles' => env('ANALYTICS_ALLOW_PERSONAL_PROFILES', false),
        'allow_fingerprinting' => env('ANALYTICS_ALLOW_FINGERPRINTING', false),
        'allow_mailbox_content' => env('ANALYTICS_ALLOW_MAILBOX_CONTENT', false),
        'allow_email_addresses' => env('ANALYTICS_ALLOW_EMAIL_ADDRESSES', false),
        'external_providers_enabled' => env('ANALYTICS_EXTERNAL_PROVIDERS_ENABLED', false),
    ],

    'certification' => [
        'analytics' => env('ANALYTICS_CERTIFY_ANALYTICS', true),
        'conversion' => env('ANALYTICS_CERTIFY_CONVERSION', true),
        'journey' => env('ANALYTICS_CERTIFY_JOURNEY', true),
        'retention' => env('ANALYTICS_CERTIFY_RETENTION', true),
    ],
];
