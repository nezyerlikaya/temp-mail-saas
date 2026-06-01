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
    'readiness' => [
        'account_health' => [
            'attention_score' => env('ENTERPRISE_HEALTH_ATTENTION_SCORE', 2),
            'risk_score' => env('ENTERPRISE_HEALTH_RISK_SCORE', 5),
            'inactive_organization_weight' => env('ENTERPRISE_HEALTH_INACTIVE_ORGANIZATION_WEIGHT', 1),
            'suspended_organization_weight' => env('ENTERPRISE_HEALTH_SUSPENDED_ORGANIZATION_WEIGHT', 3),
            'inactive_membership_weight' => env('ENTERPRISE_HEALTH_INACTIVE_MEMBERSHIP_WEIGHT', 1),
            'billing_issue_weight' => env('ENTERPRISE_HEALTH_BILLING_ISSUE_WEIGHT', 2),
            'support_issue_weight' => env('ENTERPRISE_HEALTH_SUPPORT_ISSUE_WEIGHT', 1),
            'operational_risk_weight' => env('ENTERPRISE_HEALTH_OPERATIONAL_RISK_WEIGHT', 1),
        ],
        'lifecycle' => [
            'onboarding_ready' => env('ENTERPRISE_LIFECYCLE_ONBOARDING_READY', true),
            'growth_ready' => env('ENTERPRISE_LIFECYCLE_GROWTH_READY', true),
            'billing_ready' => env('ENTERPRISE_LIFECYCLE_BILLING_READY', true),
            'suspension_ready' => env('ENTERPRISE_LIFECYCLE_SUSPENSION_READY', true),
            'recovery_ready' => env('ENTERPRISE_LIFECYCLE_RECOVERY_READY', true),
        ],
        'governance' => [
            'roles_ready' => env('ENTERPRISE_GOVERNANCE_ROLES_READY', true),
            'permissions_ready' => env('ENTERPRISE_GOVERNANCE_PERMISSIONS_READY', true),
            'ownership_ready' => env('ENTERPRISE_GOVERNANCE_OWNERSHIP_READY', true),
        ],
        'membership' => [
            'growth_window_days' => env('ENTERPRISE_MEMBERSHIP_GROWTH_WINDOW_DAYS', 30),
            'inactive_warning_count' => env('ENTERPRISE_MEMBERSHIP_INACTIVE_WARNING_COUNT', 1),
        ],
    ],
];
