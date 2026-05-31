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

    'enabled' => env('INBOUND_ENABLED', false),
    'provider' => env('INBOUND_MAIL_PROVIDER', 'local'),
    'default_provider' => env('INBOUND_DEFAULT_PROVIDER', env('INBOUND_MAIL_PROVIDER', 'local')),
    'driver' => env('INBOUND_MAIL_DRIVER', env('INBOUND_MAIL_PROVIDER', 'local')),
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
        'max_payload_kb' => env('INBOUND_MAX_PAYLOAD_KB', 2048),
        'source_ip_hash_salt' => env('INBOUND_SOURCE_IP_HASH_SALT', env('APP_KEY', 'local-inbound')),
        'intake_retention_minutes' => env('INBOUND_INTAKE_RETENTION_MINUTES', 10080),
    ],
    'providers' => [
        'local' => [
            'token' => env('LOCAL_INBOUND_TOKEN'),
            'allow_unsigned' => env('LOCAL_INBOUND_ALLOW_UNSIGNED', true),
        ],
        'mailgun' => [
            'signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),
        ],
        'postmark' => [
            'signing_key' => env('POSTMARK_WEBHOOK_SIGNING_KEY'),
        ],
        'ses' => [
            'signing_key' => env('SES_WEBHOOK_SIGNING_KEY'),
        ],
        'custom' => [
            'signing_key' => env('CUSTOM_INBOUND_SIGNING_KEY'),
        ],
    ],
    'drivers' => [],
];
