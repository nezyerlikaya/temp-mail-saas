<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Foundation
    |--------------------------------------------------------------------------
    |
    | Shared defaults for future pages, posts, announcements, help center
    | entries, SEO content, and marketing content. No UI is enabled here.
    |
    */

    'default_status' => env('CONTENT_DEFAULT_STATUS', 'draft'),

    'types' => [
        'page',
        'post',
        'announcement',
        'help',
    ],

    'slug' => [
        'separator' => env('CONTENT_SLUG_SEPARATOR', '-'),
        'max_length' => env('CONTENT_SLUG_MAX_LENGTH', 160),
    ],

    'seo' => [
        'meta_title_max_length' => env('CONTENT_META_TITLE_MAX_LENGTH', 70),
        'meta_description_max_length' => env('CONTENT_META_DESCRIPTION_MAX_LENGTH', 160),
    ],

    'publishing' => [
        'allow_scheduled' => env('CONTENT_ALLOW_SCHEDULED', true),
        'archive_published_content' => env('CONTENT_ALLOW_ARCHIVE_PUBLISHED', true),
    ],
];
