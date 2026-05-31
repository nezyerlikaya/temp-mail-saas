<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inbound Mail Placeholder
    |--------------------------------------------------------------------------
    |
    | Reserved for future inbound adapters such as webhook, IMAP, SMTP pipe, or
    | provider-specific integrations. This step does not receive email.
    |
    */

    'provider' => env('INBOUND_MAIL_PROVIDER', 'null'),
    'driver' => env('INBOUND_MAIL_DRIVER', env('INBOUND_MAIL_PROVIDER', 'null')),
    'queue' => [
        'enabled' => env('INBOUND_QUEUE_ENABLED', false),
        'connection' => env('INBOUND_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name' => env('INBOUND_QUEUE_NAME', 'inbound-mail'),
    ],
    'storage' => [
        'sources' => [
            'manual',
            'webhook',
            'smtp',
            'imap',
            'provider',
        ],
        'default_source' => env('INBOUND_STORAGE_SOURCE', 'manual'),
        'max_text_body_kb' => env('INBOUND_MAX_TEXT_BODY_KB', 512),
        'max_html_body_kb' => env('INBOUND_MAX_HTML_BODY_KB', 1024),
        'max_attachment_metadata_count' => env('INBOUND_MAX_ATTACHMENT_METADATA_COUNT', 25),
    ],
    'drivers' => [],
];
