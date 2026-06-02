<?php

return [
    'readiness_scores' => [
        'enterprise' => env('ORGANIZATION_ROADMAP_ENTERPRISE_SCORE', 62),
        'organization' => env('ORGANIZATION_ROADMAP_ORGANIZATION_SCORE', 48),
        'governance' => env('ORGANIZATION_ROADMAP_GOVERNANCE_SCORE', 66),
        'security' => env('ORGANIZATION_ROADMAP_SECURITY_SCORE', 70),
        'multi_tenant' => env('ORGANIZATION_ROADMAP_MULTI_TENANT_SCORE', 35),
    ],
    'future_account_models' => [
        'personal' => [
            'label' => 'Personal Account',
            'fit' => 'ready',
            'notes' => 'Current user, membership tier, mailbox, and billing foundations fit single-user ownership.',
        ],
        'team' => [
            'label' => 'Team Account',
            'fit' => 'planning-needed',
            'notes' => 'Requires organization membership, team roles, shared workspaces, and delegated administration planning.',
        ],
        'business' => [
            'label' => 'Business Organization',
            'fit' => 'planning-needed',
            'notes' => 'Requires workspace and department structures, account governance, billing ownership, and organization audit trails.',
        ],
        'enterprise' => [
            'label' => 'Enterprise Organization',
            'fit' => 'major-foundation-needed',
            'notes' => 'Requires SSO-ready identity boundaries, access reviews, organization security controls, compliance readiness, and tenant strategy.',
        ],
    ],
    'future_entities' => [
        'organizations' => ['Acme Inc.', 'Example Agency', 'Demo Corp'],
        'workspaces' => ['Marketing', 'Operations', 'Security', 'Development'],
        'teams' => ['Support Team', 'Moderation Team', 'Infrastructure Team'],
        'roles' => ['Owner', 'Organization Admin', 'Workspace Admin', 'Team Lead', 'Member', 'Guest'],
    ],
    'billing_models' => [
        'seat_based' => [
            'fit' => 'high',
            'advantages' => ['Predictable revenue', 'Natural fit for teams', 'Easy procurement story'],
            'disadvantages' => ['Needs seat lifecycle', 'Needs invite and removal workflows', 'Requires proration policy'],
        ],
        'workspace_billing' => [
            'fit' => 'medium',
            'advantages' => ['Aligns with departments', 'Supports agencies managing multiple clients'],
            'disadvantages' => ['Harder to explain', 'Can penalize organizational structure'],
        ],
        'usage_billing' => [
            'fit' => 'medium',
            'advantages' => ['Matches mailbox, domain, API, and retention usage', 'Scales with value'],
            'disadvantages' => ['Requires metering accuracy', 'More support questions', 'Needs cost transparency'],
        ],
        'hybrid_billing' => [
            'fit' => 'highest',
            'advantages' => ['Combines seats with usage controls', 'Best enterprise flexibility', 'Supports free/member/premium evolution'],
            'disadvantages' => ['Most complex operations model', 'Needs clear billing guardrails'],
        ],
    ],
    'tenancy_options' => [
        'single_database' => [
            'fit' => 'short-term',
            'operational_cost' => 'low',
            'maintenance_load' => 'low',
            'migration_complexity' => 'low',
            'suitability' => 'Good for personal and early team accounts, weak for strict enterprise isolation.',
        ],
        'shared_database' => [
            'fit' => 'recommended-starting-point',
            'operational_cost' => 'medium',
            'maintenance_load' => 'medium',
            'migration_complexity' => 'medium',
            'suitability' => 'Best likely v1.1 path if organization_id scoping is introduced carefully in the future.',
        ],
        'schema_per_tenant' => [
            'fit' => 'selective-enterprise',
            'operational_cost' => 'high',
            'maintenance_load' => 'high',
            'migration_complexity' => 'high',
            'suitability' => 'Useful for stronger enterprise isolation, expensive for frequent schema changes.',
        ],
        'database_per_tenant' => [
            'fit' => 'enterprise-only',
            'operational_cost' => 'very-high',
            'maintenance_load' => 'very-high',
            'migration_complexity' => 'very-high',
            'suitability' => 'Strongest isolation, likely too heavy before enterprise revenue validates demand.',
        ],
    ],
    'critical_gaps' => [
        'Organization entity and membership lifecycle are not implemented.',
        'Workspace, department, and team boundaries are not implemented.',
        'Delegated organization administration is not implemented.',
        'Organization billing ownership and seat lifecycle are not implemented.',
        'Tenant isolation strategy is not implemented.',
        'Enterprise SSO, MFA policy, access reviews, and organization audit trails are not implemented.',
    ],
    'step67' => [
        'recommended_next_phase' => 'v1.1 Enterprise & Organization Domain Model Specification',
        'focus' => 'Define non-breaking future organization entities, role boundaries, billing ownership, and tenancy decision records before implementation.',
    ],
];
