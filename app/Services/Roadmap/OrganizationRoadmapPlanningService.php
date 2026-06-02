<?php

namespace App\Services\Roadmap;

use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class OrganizationRoadmapPlanningService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'current_system' => $this->currentSystem(),
            'future_account_models' => config('organization-roadmap.future_account_models', []),
            'future_entities' => config('organization-roadmap.future_entities', []),
            'delegated_administration' => $this->delegatedAdministration(),
            'billing_analysis' => config('organization-roadmap.billing_models', []),
            'governance_analysis' => $this->governanceAnalysis(),
            'security_analysis' => $this->securityAnalysis(),
            'tenancy_analysis' => config('organization-roadmap.tenancy_options', []),
            'enterprise_operations' => $this->enterpriseOperations(),
            'critical_gaps' => config('organization-roadmap.critical_gaps', []),
            'step67' => config('organization-roadmap.step67', []),
        ];
    }

    private function scores(): array
    {
        return [
            'enterprise_readiness' => (int) config('organization-roadmap.readiness_scores.enterprise', 62),
            'organization_readiness' => (int) config('organization-roadmap.readiness_scores.organization', 48),
            'governance_readiness' => (int) config('organization-roadmap.readiness_scores.governance', 66),
            'security_readiness' => (int) config('organization-roadmap.readiness_scores.security', 70),
            'multi_tenant_readiness' => (int) config('organization-roadmap.readiness_scores.multi_tenant', 35),
        ];
    }

    private function currentSystem(): array
    {
        return [
            'user_layer' => [
                'users' => Schema::hasTable('users'),
                'profiles' => Schema::hasTable('profiles'),
                'avatars' => Schema::hasTable('media'),
                'username_system' => Schema::hasColumn('users', 'username'),
                'membership_tiers' => Schema::hasColumn('users', 'account_tier') || Schema::hasTable('plans'),
            ],
            'rbac_layer' => [
                'staff_users' => Schema::hasTable('staff_users'),
                'roles' => Schema::hasTable('roles'),
                'permissions' => Schema::hasTable('permissions'),
                'role_assignments' => Schema::hasTable('role_staff_user'),
            ],
            'billing_layer' => [
                'plans' => Schema::hasTable('plans'),
                'subscriptions' => Schema::hasTable('billing_subscriptions'),
                'invoices' => Schema::hasTable('billing_invoices'),
            ],
            'domain_layer' => [
                'domains' => Schema::hasTable('domains'),
                'domain_health' => Schema::hasTable('domain_health_checks'),
                'domain_policies' => Schema::hasTable('domain_policies'),
            ],
            'mailbox_layer' => [
                'temporary_mailboxes' => Schema::hasTable('email_messages'),
                'retention_rules' => (bool) config('retention.enabled', true),
                'mail_lifecycle' => Schema::hasTable('inbound_mail_intakes'),
            ],
            'security_layer' => [
                'abuse_detection' => Schema::hasTable('abuse_events'),
                'rate_limiting' => true,
                'audit_logging' => Schema::hasTable('operations_events'),
                'security_events' => Schema::hasTable('security_events') || Schema::hasTable('operations_events'),
            ],
            'automation_layer' => [
                'automation_rules' => Schema::hasTable('automation_rules'),
                'automation_executions' => Schema::hasTable('automation_executions'),
                'intelligence_scores' => Schema::hasTable('intelligence_scores'),
            ],
        ];
    }

    private function delegatedAdministration(): array
    {
        return [
            'owner' => 'Full organization ownership, billing authority, security authority, and lifecycle control.',
            'organization_admin' => 'Daily organization administration, member management, workspace settings, and operational review.',
            'workspace_admin' => 'Scoped administration for one workspace or department boundary.',
            'team_lead' => 'Team-level membership coordination and workflow oversight.',
            'member' => 'Standard access to assigned workspaces, mailboxes, and workflows.',
            'guest' => 'Restricted access with short-lived or limited permissions.',
        ];
    }

    private function governanceAnalysis(): array
    {
        return [
            'access_governance' => 'Needs organization-scoped roles, access reviews, and delegated permission boundaries.',
            'policy_management' => 'Needs organization policies for retention, domains, API usage, and security controls.',
            'compliance_readiness' => 'Needs evidence exports, audit trails, and documented retention decisions.',
            'audit_readiness' => 'Current operations events help, but organization audit trails are not implemented.',
            'data_retention_governance' => 'Current retention tiers help, but organization-owned retention policy is future work.',
            'security_controls' => 'Needs enterprise MFA policy, session governance, access review, and SSO readiness.',
        ];
    }

    private function securityAnalysis(): array
    {
        return [
            'sso' => 'Future only. No SAML or OIDC implementation in STEP66.',
            'saml' => 'Useful for enterprise procurement, requires identity provider metadata lifecycle and safe certificate handling.',
            'oidc' => 'Useful for modern enterprise identity, requires issuer/client configuration and callback governance.',
            'enterprise_mfa' => 'Should be policy-driven per organization in future implementation.',
            'device_policies' => 'Future organization security control for sensitive admin workflows.',
            'session_governance' => 'Needs organization-level timeout, revocation, and suspicious-session review.',
            'access_reviews' => 'Needed for enterprise governance and delegated administration.',
            'organization_audit_trails' => 'Needed before enterprise compliance claims.',
            'delegated_security_administration' => 'Should separate owner, security admin, and workspace admin responsibilities.',
        ];
    }

    private function enterpriseOperations(): array
    {
        return [
            'organization_dashboard' => 'Future aggregate health, billing, usage, security, and activity overview.',
            'team_activity_monitoring' => 'Future aggregate-only activity visibility with privacy safeguards.',
            'organization_audit_center' => 'Future organization-scoped audit trail and export readiness.',
            'organization_security_center' => 'Future SSO, MFA, session, device, and access-review workflows.',
            'compliance_dashboard' => 'Future evidence, policy, retention, and audit readiness summary.',
            'usage_analytics' => 'Future mailbox, domain, API, and retention usage rollups.',
            'cost_analytics' => 'Future seat, workspace, and usage cost visibility.',
        ];
    }
}
