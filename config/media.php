<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Storage Foundation
    |--------------------------------------------------------------------------
    |
    | Metadata-only media settings. Upload handling, image processing,
    | thumbnails, and downloads are intentionally deferred to future steps.
    |
    */

    'default_disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
    'storage_driver' => env('MEDIA_STORAGE_DRIVER', 'local'),

    'visibility' => [
        'default' => env('MEDIA_DEFAULT_VISIBILITY', 'private'),
        'public_directories' => [
            'blog',
            'seo',
            'system',
        ],
    ],

    'paths' => [
        'avatars' => 'avatars',
        'blog' => 'blog',
        'seo' => 'seo',
        'content' => 'content',
        'system' => 'system',
        'attachments' => 'attachments',
    ],

    'images' => [
        'mime_whitelist' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/svg+xml',
        ],
    ],

    'limits' => [
        'default_max_kb' => env('MEDIA_DEFAULT_MAX_KB', 10240),
        'avatar_max_kb' => env('MEDIA_AVATAR_MAX_KB', 2048),
        'attachment_max_kb' => env('MEDIA_ATTACHMENT_MAX_KB', 25600),
    ],

    'cloud' => [
        's3_compatible' => true,
        'cdn_url' => env('MEDIA_CDN_URL'),
    ],
];
