<?php

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
        'local' => App\Services\Integrations\Connectors\LocalConnector::class,
    ],
];
