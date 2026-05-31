<?php

use App\Services\Mail\Providers\LocalInboundProvider;
use App\Services\Mail\Providers\MailgunInboundProvider;
use App\Services\Mail\Providers\PostmarkInboundProvider;
use App\Services\Mail\Providers\SesInboundProvider;

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

    'activation' => [
        'states' => [
            'local' => env('LOCAL_PROVIDER_ACTIVATION_STATE', 'active'),
            'mailgun' => env('MAILGUN_PROVIDER_ACTIVATION_STATE', 'inactive'),
            'postmark' => env('POSTMARK_PROVIDER_ACTIVATION_STATE', 'inactive'),
            'ses' => env('SES_PROVIDER_ACTIVATION_STATE', 'inactive'),
            'amazon_ses' => env('SES_PROVIDER_ACTIVATION_STATE', 'inactive'),
        ],
        'allowed_states' => ['inactive', 'staging', 'ready', 'active', 'suspended'],
        'safety' => [
            'require_staging_passed' => env('PROVIDER_ACTIVATION_REQUIRE_STAGING_PASSED', true),
            'require_webhook_ready' => env('PROVIDER_ACTIVATION_REQUIRE_WEBHOOK_READY', true),
            'require_queue_ready' => env('PROVIDER_ACTIVATION_REQUIRE_QUEUE_READY', true),
            'require_installer_ready' => env('PROVIDER_ACTIVATION_REQUIRE_INSTALLER_READY', true),
            'allow_active_without_signing_key' => env('PROVIDER_ACTIVATION_ALLOW_UNSIGNED', false),
        ],
        'readiness' => [
            'providers' => array_filter(array_map(
                'trim',
                explode(',', env('PROVIDER_ACTIVATION_PROVIDERS', 'mailgun,postmark,ses')),
            )),
            'metrics_enabled' => env('PROVIDER_ACTIVATION_METRICS_ENABLED', true),
        ],
    ],

    'live_activation' => [
        'providers' => array_filter(array_map(
            'trim',
            explode(',', env('LIVE_PROVIDER_REVIEW_PROVIDERS', 'mailgun,postmark,ses')),
        )),
        'require_active_state' => env('LIVE_PROVIDER_REQUIRE_ACTIVE_STATE', true),
        'require_enabled_provider' => env('LIVE_PROVIDER_REQUIRE_ENABLED', true),
        'require_signing_secret' => env('LIVE_PROVIDER_REQUIRE_SIGNING_SECRET', true),
        'require_worker_queue' => env('LIVE_PROVIDER_REQUIRE_WORKER_QUEUE', true),
        'require_active_domain' => env('LIVE_PROVIDER_REQUIRE_ACTIVE_DOMAIN', true),
        'webhook' => [
            'require_installer_middleware' => env('LIVE_PROVIDER_REQUIRE_INSTALLER_MIDDLEWARE', true),
            'require_signature_verification' => env('LIVE_PROVIDER_REQUIRE_SIGNATURE_VERIFICATION', true),
            'require_replay_protection' => env('LIVE_PROVIDER_REQUIRE_REPLAY_PROTECTION', true),
            'require_duplicate_protection' => env('LIVE_PROVIDER_REQUIRE_DUPLICATE_PROTECTION', true),
            'require_queue_handoff' => env('LIVE_PROVIDER_REQUIRE_QUEUE_HANDOFF', true),
        ],
        'rollback' => [
            'fallback_provider' => env('LIVE_PROVIDER_FALLBACK_PROVIDER', 'local'),
            'require_fallback_ready' => env('LIVE_PROVIDER_REQUIRE_FALLBACK_READY', true),
            'suspension_ready' => env('LIVE_PROVIDER_SUSPENSION_READY', true),
            'queue_drain_documented' => env('LIVE_PROVIDER_QUEUE_DRAIN_DOCUMENTED', true),
            'rollback_documented' => env('LIVE_PROVIDER_ROLLBACK_DOCUMENTED', true),
        ],
        'observability' => [
            'metrics_required' => env('LIVE_PROVIDER_METRICS_REQUIRED', true),
            'operations_events_required' => env('LIVE_PROVIDER_EVENTS_REQUIRED', true),
        ],
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
            'class' => LocalInboundProvider::class,
            'metadata' => [
                'supports_signatures' => true,
                'supports_attachments' => true,
                'live_api' => false,
            ],
        ],
        'mailgun' => [
            'enabled' => env('MAILGUN_INBOUND_ENABLED', false),
            'class' => MailgunInboundProvider::class,
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
            'class' => PostmarkInboundProvider::class,
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
            'class' => SesInboundProvider::class,
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
            'class' => SesInboundProvider::class,
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
