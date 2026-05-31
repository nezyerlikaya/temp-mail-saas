<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SEO Defaults
    |--------------------------------------------------------------------------
    |
    | Shared metadata defaults for Blade pages. Dedicated SEO services may
    | extend these values later without changing public routes.
    |
    */

    'title' => env('SEO_DEFAULT_TITLE', env('APP_NAME', 'Temp Mail SaaS')),
    'description' => env('SEO_DEFAULT_DESCRIPTION', 'A modular foundation for a temporary email SaaS platform.'),
    'robots' => env('SEO_ROBOTS', 'index,follow'),
    'defaults' => [
        'title' => env('SEO_DEFAULT_TITLE', env('APP_NAME', 'Temp Mail SaaS')),
        'description' => env('SEO_DEFAULT_DESCRIPTION', 'A modular foundation for a temporary email SaaS platform.'),
        'robots' => env('SEO_ROBOTS', 'index,follow'),
    ],
];
