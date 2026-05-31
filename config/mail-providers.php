<?php

return [
    'default' => env('MAIL_PROVIDER_DEFAULT', env('INBOUND_DEFAULT_PROVIDER', 'local')),

    'signature_tolerance_seconds' => env('MAIL_PROVIDER_SIGNATURE_TOLERANCE_SECONDS', 300),

    'sandbox' => [
        'enabled' => env('MAIL_PROVIDER_SANDBOX_ENABLED', true),
        'accept_test_signatures' => env('MAIL_PROVIDER_SANDBOX_ACCEPT_TEST_SIGNATURES', true),
        'allowed_providers' => array_filter(array_map(
            'trim',
            explode(',', env('MAIL_PROVIDER_SANDBOX_ALLOWED_PROVIDERS', 'mailgun,postmark,amazon_ses,ses')),
        )),
        'replay_window_seconds' => env('MAIL_PROVIDER_SANDBOX_REPLAY_WINDOW_SECONDS', 300),
        'payload_logging_enabled' => env('MAIL_PROVIDER_SANDBOX_PAYLOAD_LOGGING_ENABLED', false),
        'observability_enabled' => env('MAIL_PROVIDER_SANDBOX_OBSERVABILITY_ENABLED', true),
        'test_signing_keys' => [
            'mailgun' => env('MAIL_PROVIDER_SANDBOX_MAILGUN_KEY', 'sandbox-mailgun-signing-key'),
            'postmark' => env('MAIL_PROVIDER_SANDBOX_POSTMARK_KEY', 'sandbox-postmark-signing-key'),
            'ses' => env('MAIL_PROVIDER_SANDBOX_SES_KEY', 'sandbox-ses-signing-key'),
            'amazon_ses' => env('MAIL_PROVIDER_SANDBOX_SES_KEY', 'sandbox-ses-signing-key'),
        ],
    ],

    'staging' => [
        'mode' => env('MAIL_PROVIDER_STAGING_MODE', false),
        'allowed_domains' => array_filter(array_map(
            'trim',
            explode(',', env('MAIL_PROVIDER_STAGING_ALLOWED_DOMAINS', 'example.test')),
        )),
        'provider_validation' => env('MAIL_PROVIDER_STAGING_PROVIDER_VALIDATION', true),
        'webhook_validation' => env('MAIL_PROVIDER_STAGING_WEBHOOK_VALIDATION', true),
        'metrics_enabled' => env('MAIL_PROVIDER_STAGING_METRICS_ENABLED', true),
    ],

    'webhooks' => [
        'enabled' => env('MAIL_PROVIDER_WEBHOOKS_ENABLED', true),
        'paths' => [
            'mailgun' => '/webhooks/mailgun',
            'postmark' => '/webhooks/postmark',
            'ses' => '/webhooks/ses',
        ],
    ],

    'throughput' => [
        'queue_pending_warning' => env('MAIL_PROVIDER_QUEUE_PENDING_WARNING', 100),
        'intake_per_minute_warning' => env('MAIL_PROVIDER_INTAKE_PER_MINUTE_WARNING', 120),
        'cleanup_chunk_recommendation' => env('MAIL_PROVIDER_CLEANUP_CHUNK_RECOMMENDATION', 100),
    ],

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
            'max_payload_kb' => env('MAILGUN_MAX_PAYLOAD_KB', 2048),
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
            'max_payload_kb' => env('POSTMARK_MAX_PAYLOAD_KB', 2048),
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
            'max_payload_kb' => env('SES_MAX_PAYLOAD_KB', 2048),
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
            'max_payload_kb' => env('SES_MAX_PAYLOAD_KB', 2048),
        ],
    ],
];
