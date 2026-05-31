<?php

return [
    'organizations' => [
        'enabled' => env('ENTERPRISE_ORGANIZATIONS_ENABLED', true),
        'tenant_context_session_key' => env('ENTERPRISE_TENANT_CONTEXT_SESSION_KEY', 'enterprise.organization_id'),
        'default_role' => env('ENTERPRISE_DEFAULT_ORGANIZATION_ROLE', 'member'),
    ],
    'plans' => [
        'enterprise_placeholder' => env('ENTERPRISE_PLAN_PLACEHOLDER', 'premium'),
    ],
    'sso' => [
        'enabled' => env('ENTERPRISE_SSO_ENABLED', false),
        'providers' => [],
    ],
    'custom_domains' => [
        'enabled' => env('ENTERPRISE_CUSTOM_DOMAINS_ENABLED', false),
        'verification_placeholder' => true,
    ],
];
