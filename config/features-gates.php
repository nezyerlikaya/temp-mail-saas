<?php

return [
    'default_plan' => 'free',
    'plans' => [
        'free' => [
            'mailbox_generation_limit' => 10,
            'retention_tier' => 'short',
            'polling_interval' => 15000,
            'allowed_domains' => ['example.test'],
            'domain_tiers' => ['free'],
            'priority_processing_placeholder' => false,
            'api_enabled' => false,
            'api_rate_limit_per_minute' => 5,
        ],
        'member' => [
            'mailbox_generation_limit' => 20,
            'retention_tier' => 'standard',
            'polling_interval' => 10000,
            'allowed_domains' => ['example.test'],
            'domain_tiers' => ['free', 'member'],
            'priority_processing_placeholder' => false,
            'api_enabled' => true,
            'api_rate_limit_per_minute' => 60,
        ],
        'premium' => [
            'mailbox_generation_limit' => 60,
            'retention_tier' => 'premium',
            'polling_interval' => 5000,
            'allowed_domains' => ['example.test'],
            'domain_tiers' => ['free', 'member', 'premium'],
            'priority_processing_placeholder' => true,
            'api_enabled' => true,
            'api_rate_limit_per_minute' => 300,
        ],
    ],
];
