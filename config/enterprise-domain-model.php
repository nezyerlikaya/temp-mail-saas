<?php

return [
    'maturity_scores' => [
        'organization_domain' => env('ENTERPRISE_DOMAIN_ORGANIZATION_SCORE', 58),
        'workspace_domain' => env('ENTERPRISE_DOMAIN_WORKSPACE_SCORE', 45),
        'membership_domain' => env('ENTERPRISE_DOMAIN_MEMBERSHIP_SCORE', 52),
        'governance_domain' => env('ENTERPRISE_DOMAIN_GOVERNANCE_SCORE', 50),
        'enterprise_security_domain' => env('ENTERPRISE_DOMAIN_SECURITY_SCORE', 55),
        'tenant_boundary' => env('ENTERPRISE_DOMAIN_TENANT_BOUNDARY_SCORE', 40),
    ],
    'domains' => [
        'organization' => [
            'future_entity' => 'Organization',
            'example_fields' => ['name', 'slug', 'status', 'owner', 'settings', 'security_profile'],
            'purpose' => 'Top-level customer-owned business boundary for future enterprise accounts.',
            'implementation_status' => 'specification-only',
        ],
        'workspace' => [
            'future_entity' => 'Workspace',
            'examples' => ['Marketing', 'Operations', 'Security', 'Development'],
            'purpose' => 'Scoped work area for resource ownership, operational separation, and delegated administration.',
            'implementation_status' => 'specification-only',
        ],
        'team' => [
            'future_entity' => 'Team',
            'examples' => ['Moderation', 'Support', 'Infrastructure', 'Compliance'],
            'purpose' => 'People grouping inside an organization or workspace for responsibility and workflow routing.',
            'implementation_status' => 'specification-only',
        ],
        'membership' => [
            'future_entity' => 'Membership',
            'states' => ['invited', 'pending', 'active', 'suspended', 'removed'],
            'purpose' => 'Lifecycle record connecting users to organizations, workspaces, teams, and roles.',
            'implementation_status' => 'specification-only',
        ],
        'seat' => [
            'future_entity' => 'Seat',
            'states' => ['assigned', 'unassigned', 'reserved', 'suspended'],
            'purpose' => 'Billing and capacity unit for organization users.',
            'implementation_status' => 'specification-only',
        ],
    ],
    'role_model' => [
        'organization_owner' => 'Full organization authority, billing ownership, security ownership, lifecycle control.',
        'organization_admin' => 'Daily organization management without ultimate ownership transfer rights.',
        'workspace_admin' => 'Scoped administration within assigned workspace boundaries.',
        'team_lead' => 'Team membership coordination and workflow oversight.',
        'member' => 'Standard access to assigned organization resources.',
        'guest' => 'Restricted, temporary, or external access.',
    ],
    'billing_domain' => [
        'seat_based' => [
            'fit' => 'high',
            'recommendation' => 'Best first enterprise billing primitive because it maps cleanly to members.',
        ],
        'usage_based' => [
            'fit' => 'medium',
            'recommendation' => 'Useful for mailbox, domain, API, and retention overage once metering matures.',
        ],
        'hybrid' => [
            'fit' => 'highest',
            'recommendation' => 'Recommended long-term model: seats plus controlled usage limits and overages.',
        ],
    ],
    'governance_domain' => [
        'policy' => 'Organization-owned rule for security, retention, domain use, API use, or billing limits.',
        'access_review' => 'Recurring review of organization roles, workspace access, team access, and privileged users.',
        'approval_workflow' => 'Controlled path for sensitive changes such as owner transfer, domain activation, or security policy change.',
        'compliance_event' => 'Evidence-friendly record for governance-relevant changes.',
        'audit_event' => 'Organization-scoped audit record for user, billing, security, API, mailbox, and domain activity.',
        'security_review' => 'Periodic organization security posture review.',
    ],
    'security_domain' => [
        'saml_sso' => 'Future identity provider federation with metadata and certificate lifecycle.',
        'oidc_sso' => 'Future issuer/client based federation for modern identity providers.',
        'enterprise_mfa' => 'Future organization policy requiring stronger authentication controls.',
        'session_governance' => 'Future session lifetime, revocation, and suspicious session review policy.',
        'device_governance' => 'Future device posture and trusted-device policy.',
        'security_policies' => 'Future organization security profile for access, retention, API, and mailbox controls.',
    ],
    'tenant_boundaries' => [
        'user_boundary' => 'Personal identity and account-level ownership remains separate from organization membership.',
        'organization_boundary' => 'Future top-level customer ownership boundary for billing, governance, and security.',
        'workspace_boundary' => 'Future scoped resource boundary inside an organization.',
        'team_boundary' => 'Future people and responsibility boundary, not necessarily a data isolation boundary.',
        'resource_boundary' => 'Future ownership boundary for mailboxes, domains, API keys, audit logs, reports, and billing resources.',
    ],
    'resource_ownership' => ['mailboxes', 'domains', 'api_keys', 'audit_logs', 'reports', 'billing_resources'],
    'domain_events' => [
        'OrganizationCreated',
        'MemberInvited',
        'SeatAssigned',
        'WorkspaceCreated',
        'TeamCreated',
        'OrganizationSuspended',
    ],
    'future_api_domains' => [
        'organization_api',
        'workspace_api',
        'membership_api',
        'team_api',
        'governance_api',
        'audit_api',
    ],
    'critical_gaps' => [
        'No future organization domain implementation contract exists yet.',
        'No workspace ownership or isolation contract exists yet.',
        'No membership lifecycle implementation contract exists yet.',
        'No seat lifecycle or billing ownership contract exists yet.',
        'No enterprise governance policy model exists yet.',
        'No tenant boundary enforcement strategy exists yet.',
    ],
    'step68' => [
        'recommended_next_phase' => 'v1.1 Enterprise Data Ownership & Access Policy Specification',
        'focus' => 'Define organization-scoped ownership, access policies, audit boundaries, and non-breaking implementation sequencing.',
    ],
];
