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
    'site_name' => env('SEO_SITE_NAME', env('APP_NAME', 'Temp Mail SaaS')),
    'sitemap' => [
        'enabled' => env('SEO_SITEMAP_ENABLED', true),
        'static_pages' => ['home', 'status', 'inbox.index'],
    ],
    'structured_data' => [
        'enabled' => env('SEO_STRUCTURED_DATA_ENABLED', true),
        'organization_name' => env('SEO_ORGANIZATION_NAME', env('APP_NAME', 'Temp Mail SaaS')),
        'organization_url' => env('APP_URL', 'http://localhost'),
    ],
    'open_graph' => [
        'type' => env('SEO_OG_TYPE', 'website'),
        'image' => env('SEO_OG_IMAGE'),
    ],
    'twitter' => [
        'card' => env('SEO_TWITTER_CARD', 'summary_large_image'),
        'image' => env('SEO_TWITTER_IMAGE'),
    ],
    'defaults' => [
        'title' => env('SEO_DEFAULT_TITLE', env('APP_NAME', 'Temp Mail SaaS')),
        'description' => env('SEO_DEFAULT_DESCRIPTION', 'A modular foundation for a temporary email SaaS platform.'),
        'robots' => env('SEO_ROBOTS', 'index,follow'),
        'canonical_url' => null,
        'og_title' => null,
        'og_description' => null,
        'og_image' => env('SEO_OG_IMAGE'),
        'twitter_title' => null,
        'twitter_description' => null,
        'twitter_image' => env('SEO_TWITTER_IMAGE'),
    ],
];
