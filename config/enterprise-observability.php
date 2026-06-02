<?php

return [
    'readiness_scores' => [
        'audit_governance' => 56,
        'observability' => 52,
        'operational_governance' => 54,
        'compliance_evidence' => 48,
        'monitoring_governance' => 51,
        'siem' => 40,
    ],

    'audit_governance' => [
        'identity_audit' => [
            'scope' => 'Future identity lifecycle and authentication governance evidence.',
            'owner' => 'security',
            'retention_expectation' => 'policy-defined',
            'export_expectation' => 'masked enterprise export',
            'review_requirement' => 'quarterly access governance review',
        ],
        'access_audit' => [
            'scope' => 'Future role, permission, and delegated access decisions.',
            'owner' => 'governance',
            'retention_expectation' => 'policy-defined',
            'export_expectation' => 'least-detail evidence bundle',
            'review_requirement' => 'access certification cycle',
        ],
        'security_audit' => [
            'scope' => 'Future abuse, session, device, and security-control reviews.',
            'owner' => 'security',
            'retention_expectation' => 'security-retention tier',
            'export_expectation' => 'security evidence bundle',
            'review_requirement' => 'incident-driven review',
        ],
        'compliance_audit' => [
            'scope' => 'Future compliance review, evidence capture, and control attestations.',
            'owner' => 'compliance',
            'retention_expectation' => 'compliance-retention tier',
            'export_expectation' => 'compliance evidence bundle',
            'review_requirement' => 'scheduled compliance review',
        ],
        'billing_audit' => [
            'scope' => 'Future seat, plan, invoice, and billing-governance decisions.',
            'owner' => 'billing operations',
            'retention_expectation' => 'financial-retention tier',
            'export_expectation' => 'billing audit summary',
            'review_requirement' => 'billing operations review',
        ],
        'configuration_audit' => [
            'scope' => 'Future enterprise policy, provider, domain, and operational settings changes.',
            'owner' => 'platform operations',
            'retention_expectation' => 'configuration-retention tier',
            'export_expectation' => 'change evidence bundle',
            'review_requirement' => 'change advisory review',
        ],
        'operational_audit' => [
            'scope' => 'Future incident, rollback, launch, and monitoring governance decisions.',
            'owner' => 'operations',
            'retention_expectation' => 'operations-retention tier',
            'export_expectation' => 'operations evidence bundle',
            'review_requirement' => 'post-incident review',
        ],
        'organization_audit' => [
            'scope' => 'Future organization, workspace, team, and member governance actions.',
            'owner' => 'enterprise operations',
            'retention_expectation' => 'organization-retention tier',
            'export_expectation' => 'organization-scoped evidence bundle',
            'review_requirement' => 'organization governance review',
        ],
    ],

    'audit_event_classification' => [
        'authentication_events',
        'authorization_events',
        'permission_events',
        'provisioning_events',
        'governance_events',
        'billing_events',
        'security_events',
        'administrative_events',
        'organization_events',
        'automation_events',
        'api_events',
        'integration_events',
    ],

    'audit_evidence_model' => [
        'audit_evidence' => 'Structured evidence that proves who changed what, when, and under which governance context.',
        'security_evidence' => 'Security-control evidence with minimized identifiers and no raw payloads.',
        'governance_evidence' => 'Policy, approval, review, and certification evidence for enterprise review cycles.',
        'access_review_evidence' => 'Future access certification proof with reviewer, scope, and outcome metadata.',
        'export_evidence' => 'Proof of export request, approval, scope, and delivery status without storing exported contents.',
        'retention_evidence' => 'Retention, archival, and purge governance proof aligned to future enterprise policies.',
    ],

    'observability_domains' => [
        'metrics' => 'Aggregate service health and operational trend signals.',
        'logs' => 'Sanitized operational records with no secrets, payload bodies, or mailbox contents.',
        'traces' => 'Future request and workflow path visibility for platform operations.',
        'health_signals' => 'Application, queue, provider, domain, and scheduler readiness signals.',
        'security_signals' => 'Abuse, access, session, device, webhook, and API risk signals.',
        'governance_signals' => 'Policy review, access review, evidence, and certification readiness signals.',
        'compliance_signals' => 'Evidence completeness, retention posture, export readiness, and review-cycle signals.',
    ],

    'operational_governance' => [
        'service_ownership' => 'Every future enterprise surface should have accountable operational ownership.',
        'domain_ownership' => 'Domains, providers, billing, security, and API areas need named stewardship boundaries.',
        'escalation_ownership' => 'Incidents should map to a primary owner, backup owner, and escalation path.',
        'incident_ownership' => 'Incident records should preserve decision ownership without storing sensitive payloads.',
        'change_ownership' => 'Configuration and policy changes should carry reviewer and approver accountability.',
        'governance_ownership' => 'Governance reviews should have clear owner, cadence, scope, and evidence rules.',
    ],

    'incident_observability' => [
        'types' => [
            'security_incident',
            'abuse_incident',
            'billing_incident',
            'api_incident',
            'provider_incident',
            'domain_incident',
            'data_governance_incident',
        ],
        'lifecycle' => [
            'detected',
            'investigating',
            'mitigating',
            'resolved',
            'verified',
            'archived',
        ],
    ],

    'monitoring_governance' => [
        'availability' => 'Uptime, health endpoint, and route availability monitoring policy.',
        'reliability' => 'Error rate, latency, retry, and degradation monitoring policy.',
        'queue_health' => 'Backlog, failed job, retry, and worker readiness monitoring policy.',
        'mail_processing_health' => 'Inbound acceptance, processing, storage, and inbox visibility monitoring policy.',
        'domain_health' => 'Domain activation, onboarding, suspension, and fallback monitoring policy.',
        'security_health' => 'Abuse, authentication, authorization, and webhook risk monitoring policy.',
        'governance_health' => 'Policy review, certification, and ownership monitoring policy.',
        'compliance_health' => 'Evidence completeness, retention, export, and audit-readiness monitoring policy.',
    ],

    'operational_risks' => [
        'availability_risk' => [
            'severity' => 'high',
            'detection' => 'health and uptime signal review',
            'response_expectation' => 'incident owner review and rollback assessment',
        ],
        'reliability_risk' => [
            'severity' => 'medium',
            'detection' => 'error, latency, and retry signal review',
            'response_expectation' => 'service owner triage',
        ],
        'provider_risk' => [
            'severity' => 'high',
            'detection' => 'provider failure and webhook delivery signal review',
            'response_expectation' => 'provider rollback readiness review',
        ],
        'queue_risk' => [
            'severity' => 'high',
            'detection' => 'queue backlog and failed job signal review',
            'response_expectation' => 'queue restart and failure-drain review',
        ],
        'security_risk' => [
            'severity' => 'critical',
            'detection' => 'abuse, access, and webhook security signal review',
            'response_expectation' => 'security incident governance review',
        ],
        'abuse_risk' => [
            'severity' => 'high',
            'detection' => 'rate limit and abuse intelligence signal review',
            'response_expectation' => 'abuse mitigation and policy review',
        ],
        'compliance_risk' => [
            'severity' => 'medium',
            'detection' => 'evidence completeness and retention signal review',
            'response_expectation' => 'compliance owner review',
        ],
        'governance_risk' => [
            'severity' => 'medium',
            'detection' => 'ownership, policy, and review-cadence signal review',
            'response_expectation' => 'governance review cycle',
        ],
        'audit_risk' => [
            'severity' => 'high',
            'detection' => 'audit coverage and export-readiness signal review',
            'response_expectation' => 'audit evidence gap remediation plan',
        ],
    ],

    'compliance_evidence' => [
        'gdpr_evidence' => 'Future proof for access, deletion, export, retention, and processing governance.',
        'soc2_evidence' => 'Future proof for security, availability, confidentiality, and change controls.',
        'iso27001_evidence' => 'Future proof for risk treatment, access governance, incident response, and audit controls.',
        'enterprise_contract_evidence' => 'Future proof for customer-specific controls, retention commitments, and support obligations.',
    ],

    'dashboard_readiness' => [
        'audit_dashboard',
        'governance_dashboard',
        'security_dashboard',
        'compliance_dashboard',
        'incident_dashboard',
        'operational_dashboard',
        'risk_dashboard',
    ],

    'external_integration_readiness' => [
        'siem_export' => 'Future sanitized export path for SIEM ingestion.',
        'audit_export' => 'Future audit evidence export with scope and approval controls.',
        'security_export' => 'Future security signal export with minimized data.',
        'compliance_export' => 'Future compliance evidence export with retention and approval controls.',
        'operational_export' => 'Future operational health export for enterprise operations.',
        'evidence_export' => 'Future evidence package export for customer and auditor review.',
    ],

    'observability_audit_events' => [
        'configuration_change',
        'policy_change',
        'governance_review',
        'access_review',
        'security_review',
        'incident_review',
        'evidence_export',
        'audit_export',
        'dashboard_access',
    ],

    'critical_gaps' => [
        'No SIEM export pipeline is implemented.',
        'No enterprise audit evidence store exists yet.',
        'No compliance evidence workflow is implemented.',
        'No dashboard governance model is implemented.',
        'No monitoring governance policy engine exists.',
        'No operational accountability model is enforced.',
    ],

    'step74' => [
        'recommended_next_phase' => 'v1.1 Enterprise Reporting, Evidence Export & Dashboard Specification',
    ],
];
