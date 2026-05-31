<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Temp Mail SaaS Core Settings
    |--------------------------------------------------------------------------
    |
    | Central application-level settings for the modular monolith. Keep this
    | file focused on cross-module defaults only; feature-specific settings
    | belong in their own configuration files.
    |
    */

    'name' => env('APP_NAME', 'Temp Mail SaaS'),
    'public_name' => env('TEMPMAIL_PUBLIC_NAME', env('APP_NAME', 'Temp Mail SaaS')),
    'support_email' => env('TEMPMAIL_SUPPORT_EMAIL', 'support@example.com'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'architecture' => [
        'type' => 'modular_monolith',
        'controllers' => 'thin',
        'business_logic' => 'services',
        'contracts' => true,
        'dto_ready' => true,
        'events_ready' => true,
        'queues_ready' => true,
        'policies_ready' => true,
        'api_ready' => true,
    ],
];
