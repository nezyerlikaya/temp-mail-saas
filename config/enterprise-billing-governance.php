<?php

return [
    'readiness_scores' => [
        'enterprise_billing' => 46,
        'seat_governance' => 42,
        'subscription_governance' => 48,
        'ownership_governance' => 50,
        'cost_governance' => 41,
        'financial_governance' => 39,
    ],

    'billing_domains' => [
        'personal_billing' => [
            'scope' => 'Single-user billing ownership for future individual commercial flows.',
            'boundary' => 'User-owned plan and usage governance.',
            'owner_model' => 'personal owner',
            'review_requirement' => 'self-service billing review',
        ],
        'team_billing' => [
            'scope' => 'Small-team commercial ownership for future shared seat and usage flows.',
            'boundary' => 'Team-scoped seat allocation and cost visibility.',
            'owner_model' => 'team billing owner',
            'review_requirement' => 'team cost review',
        ],
        'organization_billing' => [
            'scope' => 'Organization-owned subscriptions, seats, usage, and contract governance.',
            'boundary' => 'Organization-scoped billing resources and approvals.',
            'owner_model' => 'organization billing owner',
            'review_requirement' => 'organization financial governance review',
        ],
        'enterprise_contract_billing' => [
            'scope' => 'Contract-driven commercial governance for enterprise customers.',
            'boundary' => 'Contract, procurement, legal, and finance review scope.',
            'owner_model' => 'contract manager',
            'review_requirement' => 'contract renewal and compliance review',
        ],
    ],

    'ownership_governance' => [
        'personal_ownership' => [
            'owner' => 'account holder',
            'controller' => 'account holder',
            'reviewer' => 'self-service review',
            'escalation_owner' => 'support operations',
        ],
        'shared_ownership' => [
            'owner' => 'resource owner',
            'controller' => 'delegated administrator',
            'reviewer' => 'workspace or team reviewer',
            'escalation_owner' => 'organization administrator',
        ],
        'organization_ownership' => [
            'owner' => 'organization owner',
            'controller' => 'billing administrator',
            'reviewer' => 'finance reviewer',
            'escalation_owner' => 'organization owner',
        ],
        'enterprise_ownership' => [
            'owner' => 'enterprise contract owner',
            'controller' => 'contract manager',
            'reviewer' => 'procurement reviewer',
            'escalation_owner' => 'enterprise sponsor',
        ],
        'managed_ownership' => [
            'owner' => 'managed account owner',
            'controller' => 'customer success or operations owner',
            'reviewer' => 'account governance reviewer',
            'escalation_owner' => 'enterprise operations',
        ],
    ],

    'seat_types' => [
        'active_seat' => 'Seat assigned to an active future member.',
        'reserved_seat' => 'Seat held for a future member or department allocation.',
        'pending_seat' => 'Seat awaiting approval or invitation acceptance.',
        'suspended_seat' => 'Seat temporarily disabled while preserving governance evidence.',
        'archived_seat' => 'Historical seat record retained for audit and billing reconciliation.',
        'service_seat' => 'Seat allocated to a future service identity or automation identity.',
    ],

    'seat_lifecycle' => [
        'requested',
        'approved',
        'assigned',
        'activated',
        'suspended',
        'reclaimed',
        'archived',
    ],

    'subscription_governance' => [
        'individual_subscription' => [
            'scope' => 'Personal plan ownership and future paid-user lifecycle.',
            'governance' => 'user consent, downgrade rules, cancellation evidence',
        ],
        'team_subscription' => [
            'scope' => 'Team-level subscription planning for small shared accounts.',
            'governance' => 'team owner approval, seat allocation, cost visibility',
        ],
        'organization_subscription' => [
            'scope' => 'Organization-level subscription planning for multi-user accounts.',
            'governance' => 'billing owner approval, delegated administration, usage review',
        ],
        'enterprise_contract' => [
            'scope' => 'Contractual commercial model for enterprise organizations.',
            'governance' => 'contract renewal, suspension, termination, procurement review',
        ],
        'managed_contract' => [
            'scope' => 'High-touch enterprise account model with internal operational ownership.',
            'governance' => 'account manager review, support readiness, contract evidence',
        ],
    ],

    'billing_roles' => [
        'billing_owner' => 'Owns billing settings, commercial accountability, and escalation.',
        'billing_administrator' => 'Manages future billing operations within approved boundaries.',
        'finance_reviewer' => 'Reviews invoices, cost allocation, and budget impact.',
        'procurement_reviewer' => 'Reviews contracts, renewals, and vendor requirements.',
        'auditor' => 'Reads scoped billing evidence without modifying commercial state.',
        'organization_owner' => 'Escalation authority for organization-owned billing decisions.',
        'contract_manager' => 'Owns contract lifecycle, renewal posture, and termination readiness.',
    ],

    'cost_governance' => [
        'seat_costs' => 'Future seat allocation, reservation, suspension, and reclamation cost tracking.',
        'usage_costs' => 'Future usage-based commercial visibility for mailbox, message, and API activity.',
        'domain_costs' => 'Future cost visibility for organization-owned domains and domain pools.',
        'storage_costs' => 'Future storage and retention cost visibility.',
        'api_costs' => 'Future API usage and service identity cost attribution.',
        'enterprise_contract_costs' => 'Future contracted minimums, committed usage, and renewal impact.',
        'operational_costs' => 'Future support, incident, and managed operations cost visibility.',
    ],

    'usage_governance' => [
        'mailbox_usage' => 'Future mailbox generation and lifecycle usage policy.',
        'message_usage' => 'Future inbound message volume and processing usage policy.',
        'storage_usage' => 'Future retention, attachment, and mailbox storage policy.',
        'api_usage' => 'Future API request, automation, and service identity usage policy.',
        'domain_usage' => 'Future organization domain, pool, and provider mapping usage policy.',
        'organization_usage' => 'Future aggregate organization usage review and threshold policy.',
    ],

    'contract_model' => [
        'contract_lifecycle' => [
            'draft',
            'approved',
            'active',
            'renewal_review',
            'suspended',
            'terminated',
            'archived',
        ],
        'contract_renewal' => 'Future renewal review should include usage, support, security, and procurement signals.',
        'contract_suspension' => 'Future suspension should preserve ownership, evidence, export rights, and support escalation.',
        'contract_termination' => 'Future termination should define export, retention, revocation, and archive governance.',
        'contract_transfer' => 'Future transfer should require owner approval, finance review, and audit evidence.',
        'contract_ownership' => 'Future ownership should define sponsor, billing owner, procurement reviewer, and escalation owner.',
    ],

    'billing_risks' => [
        'over_allocation_risk' => [
            'description' => 'Seats or usage capacity exceed actual organization demand.',
            'detection' => 'reserved seat and inactive allocation review',
            'governance_requirement' => 'allocation review and reclamation policy',
            'audit_requirement' => 'seat allocation evidence',
        ],
        'under_allocation_risk' => [
            'description' => 'Seat or usage limits block legitimate team growth.',
            'detection' => 'limit pressure and support request review',
            'governance_requirement' => 'capacity approval path',
            'audit_requirement' => 'limit exception evidence',
        ],
        'seat_leakage_risk' => [
            'description' => 'Suspended or stale users continue consuming paid capacity.',
            'detection' => 'inactive member and suspended seat review',
            'governance_requirement' => 'seat reclamation cycle',
            'audit_requirement' => 'reclamation evidence',
        ],
        'billing_ownership_risk' => [
            'description' => 'No accountable owner exists for billing decisions.',
            'detection' => 'missing owner and escalation review',
            'governance_requirement' => 'billing owner assignment policy',
            'audit_requirement' => 'ownership change evidence',
        ],
        'contract_risk' => [
            'description' => 'Contract terms, renewal dates, or suspension rules are unclear.',
            'detection' => 'contract review and renewal-readiness review',
            'governance_requirement' => 'contract owner and renewal policy',
            'audit_requirement' => 'contract decision evidence',
        ],
        'usage_abuse_risk' => [
            'description' => 'Organization usage pattern creates cost, abuse, or reliability impact.',
            'detection' => 'usage threshold and abuse signal review',
            'governance_requirement' => 'usage threshold governance',
            'audit_requirement' => 'threshold change evidence',
        ],
        'cost_visibility_risk' => [
            'description' => 'Customers and operators cannot explain commercial cost drivers.',
            'detection' => 'dashboard and report coverage review',
            'governance_requirement' => 'cost attribution model',
            'audit_requirement' => 'cost governance review evidence',
        ],
    ],

    'billing_audit_events' => [
        'seat_assignment',
        'seat_revocation',
        'billing_ownership_change',
        'subscription_change',
        'contract_change',
        'usage_threshold_change',
        'cost_governance_change',
        'billing_policy_change',
    ],

    'reporting_readiness' => [
        'billing_dashboard',
        'seat_dashboard',
        'usage_dashboard',
        'cost_dashboard',
        'contract_dashboard',
        'ownership_dashboard',
    ],

    'financial_governance' => [
        'budget_controls' => 'Future budget policy, thresholds, and approval workflows.',
        'cost_visibility' => 'Future customer-facing and internal cost explainability.',
        'chargeback_readiness' => 'Future department, workspace, or team allocation model.',
        'allocation_readiness' => 'Future seat and usage allocation governance.',
        'contract_governance' => 'Future contract ownership, renewal, and suspension governance.',
        'financial_audit_readiness' => 'Future finance evidence, audit export, and review cadence.',
    ],

    'critical_gaps' => [
        'No enterprise billing ownership model is implemented.',
        'No real seat governance lifecycle exists.',
        'No organization subscription governance exists.',
        'No cost attribution or usage governance model is enforced.',
        'No enterprise contract lifecycle exists.',
        'No financial governance evidence workflow exists.',
    ],

    'step75' => [
        'recommended_next_phase' => 'v1.1 Enterprise Reporting, Evidence Export & Dashboard Specification',
    ],
];
