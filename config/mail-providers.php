<?php

return [
    'default' => env('MAIL_PROVIDER_DEFAULT', env('INBOUND_DEFAULT_PROVIDER', 'local')),

    'signature_tolerance_seconds' => env('MAIL_PROVIDER_SIGNATURE_TOLERANCE_SECONDS', 300),

    'providers' => [
        'local' => [
            'enabled' => env('LOCAL_INBOUND_ENABLED', true),
            'class' => App\Services\Mail\Providers\LocalInboundProvider::class,
            'metadata' => [
                'supports_signatures' => true,
                'supports_attachments' => true,
                'live_api' => false,
            ],
        ],
        'mailgun' => [
            'enabled' => env('MAILGUN_INBOUND_ENABLED', false),
            'class' => App\Services\Mail\Providers\MailgunInboundProvider::class,
            'signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),
            'metadata' => [
                'supports_signatures' => true,
                'supports_attachments' => true,
                'live_api' => false,
            ],
        ],
        'postmark' => [
            'enabled' => env('POSTMARK_INBOUND_ENABLED', false),
            'class' => App\Services\Mail\Providers\PostmarkInboundProvider::class,
            'signing_key' => env('POSTMARK_WEBHOOK_SIGNING_KEY'),
            'metadata' => [
                'supports_signatures' => true,
                'supports_attachments' => true,
                'live_api' => false,
            ],
        ],
        'amazon_ses' => [
            'enabled' => env('SES_INBOUND_ENABLED', false),
            'class' => App\Services\Mail\Providers\SesInboundProvider::class,
            'signing_key' => env('SES_WEBHOOK_SIGNING_KEY'),
            'metadata' => [
                'supports_signatures' => true,
                'supports_attachments' => false,
                'live_api' => false,
            ],
        ],
        'ses' => [
            'enabled' => env('SES_INBOUND_ENABLED', false),
            'class' => App\Services\Mail\Providers\SesInboundProvider::class,
            'signing_key' => env('SES_WEBHOOK_SIGNING_KEY'),
            'metadata' => [
                'alias_for' => 'amazon_ses',
                'supports_signatures' => true,
                'supports_attachments' => false,
                'live_api' => false,
            ],
        ],
    ],
];
