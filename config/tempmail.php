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
