<?php

use App\Services\Integrations\Connectors\LocalConnector;

return [
    'marketplace' => [
        'enabled' => false,
        'public_ui_enabled' => false,
        'categories' => [
            'productivity',
            'automation',
            'security',
            'analytics',
            'communication',
            'developer',
        ],
    ],

    'registry' => [
        'default_status' => 'inactive',
        'compatibility' => [
            'app' => 'Temp Mail SaaS v1',
            'minimum_version' => '1.0.0',
        ],
    ],

    'oauth' => [
        'enabled' => false,
        'redirect_route' => null,
        'state_ttl_minutes' => 10,
        'providers' => [
            'google' => [
                'enabled' => false,
                'client_id' => null,
                'scopes' => [],
            ],
            'slack' => [
                'enabled' => false,
                'client_id' => null,
                'scopes' => [],
            ],
            'discord' => [
                'enabled' => false,
                'client_id' => null,
                'scopes' => [],
            ],
        ],
    ],

    'webhooks' => [
        'enabled' => true,
        'delivery_queue' => env('INTEGRATION_WEBHOOK_QUEUE', 'default'),
        'timeout_seconds' => 10,
        'max_attempts' => 3,
        'secret_length' => 48,
        'allowed_schemes' => ['https', 'http'],
    ],

    'connectors' => [
        'local' => LocalConnector::class,
    ],
    'ecosystem' => [
        'readiness' => [
            'require_registry' => env('ECOSYSTEM_REQUIRE_REGISTRY', true),
            'require_connectors' => env('ECOSYSTEM_REQUIRE_CONNECTORS', true),
            'require_configuration' => env('ECOSYSTEM_REQUIRE_CONFIGURATION', true),
            'coverage_warning_minimum' => env('ECOSYSTEM_COVERAGE_WARNING_MINIMUM', 1),
        ],
        'connectors' => [
            'require_contracts' => env('ECOSYSTEM_CONNECTORS_REQUIRE_CONTRACTS', true),
            'allow_missing_classes' => env('ECOSYSTEM_CONNECTORS_ALLOW_MISSING_CLASSES', false),
            'inactive_connection_warning_count' => env('ECOSYSTEM_INACTIVE_CONNECTION_WARNING_COUNT', 1),
        ],
        'webhooks' => [
            'require_enabled' => env('ECOSYSTEM_WEBHOOKS_REQUIRE_ENABLED', true),
            'require_delivery_hashes' => env('ECOSYSTEM_WEBHOOKS_REQUIRE_DELIVERY_HASHES', true),
            'failed_delivery_warning_count' => env('ECOSYSTEM_WEBHOOK_FAILED_WARNING_COUNT', 1),
            'require_event_subscriptions' => env('ECOSYSTEM_WEBHOOKS_REQUIRE_EVENTS', true),
        ],
        'dependencies' => [
            'require_local_connector' => env('ECOSYSTEM_DEPENDENCIES_REQUIRE_LOCAL_CONNECTOR', true),
            'require_operations_events' => env('ECOSYSTEM_DEPENDENCIES_REQUIRE_OPERATIONS_EVENTS', true),
            'require_webhook_tables' => env('ECOSYSTEM_DEPENDENCIES_REQUIRE_WEBHOOK_TABLES', true),
        ],
    ],
];
