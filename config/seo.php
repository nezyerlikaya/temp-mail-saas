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
    'growth_readiness' => [
        'seo' => [
            'require_sitemap' => env('SEO_GROWTH_REQUIRE_SITEMAP', true),
            'require_robots' => env('SEO_GROWTH_REQUIRE_ROBOTS', true),
            'require_structured_data' => env('SEO_GROWTH_REQUIRE_STRUCTURED_DATA', true),
            'require_canonical' => env('SEO_GROWTH_REQUIRE_CANONICAL', true),
            'require_metadata' => env('SEO_GROWTH_REQUIRE_METADATA', true),
        ],
        'content' => [
            'publication_ready' => env('SEO_GROWTH_CONTENT_PUBLICATION_READY', true),
            'category_ready' => env('SEO_GROWTH_CONTENT_CATEGORY_READY', true),
            'tag_ready' => env('SEO_GROWTH_CONTENT_TAG_READY', true),
            'seo_content_ready' => env('SEO_GROWTH_CONTENT_SEO_READY', true),
            'editorial_ready' => env('SEO_GROWTH_CONTENT_EDITORIAL_READY', true),
            'require_published_content' => env('SEO_GROWTH_REQUIRE_PUBLISHED_CONTENT', false),
        ],
        'landing_pages' => [
            'homepage_route' => env('SEO_GROWTH_HOMEPAGE_ROUTE', 'home'),
            'metadata_required' => env('SEO_GROWTH_LANDING_METADATA_REQUIRED', true),
            'structured_data_required' => env('SEO_GROWTH_LANDING_STRUCTURED_DATA_REQUIRED', true),
            'discoverability_required' => env('SEO_GROWTH_LANDING_DISCOVERABILITY_REQUIRED', true),
        ],
        'indexing' => [
            'sitemap_coverage_required' => env('SEO_GROWTH_INDEXING_SITEMAP_REQUIRED', true),
            'robots_coverage_required' => env('SEO_GROWTH_INDEXING_ROBOTS_REQUIRED', true),
            'canonical_coverage_required' => env('SEO_GROWTH_INDEXING_CANONICAL_REQUIRED', true),
            'crawl_ready' => env('SEO_GROWTH_INDEXING_CRAWL_READY', true),
        ],
        'certification' => [
            'seo' => env('SEO_GROWTH_CERTIFY_SEO', true),
            'content' => env('SEO_GROWTH_CERTIFY_CONTENT', true),
            'indexing' => env('SEO_GROWTH_CERTIFY_INDEXING', true),
            'landing_page' => env('SEO_GROWTH_CERTIFY_LANDING_PAGE', true),
        ],
    ],
];
