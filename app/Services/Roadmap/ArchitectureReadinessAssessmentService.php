<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class ArchitectureReadinessAssessmentService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'risk_scores' => config('architecture-readiness.risk_scores', []),
            'architecture_layers' => $this->architectureLayers(),
            'strongest_areas' => ['core product', 'production operations', 'security', 'abuse protection'],
            'weakest_areas' => ['multi-tenant implementation', 'enterprise billing', 'compliance evidence'],
            'critical_gaps' => ['tenant isolation', 'organization model', 'enterprise identity', 'evidence export', 'seat billing'],
            'medium_gaps' => ['API DX', 'automation intelligence', 'dashboards', 'support expansion'],
            'future_opportunities' => ['Enterprise Edition', 'Multi-Tenant Edition', 'White Label Edition', 'Marketplace Edition', 'AI Edition'],
            'optional_enterprise_enhancements' => ['organizations', 'delegated access', 'SSO and SCIM', 'evidence export', 'seat billing'],
            'optional_scale_enhancements' => ['dedicated isolation', 'queue partitioning', 'provider routing'],
            'technical_debt' => $this->technicalDebt(),
            'recommendations' => $this->recommendations(),
            'roadmap_closure' => config('architecture-readiness.roadmap_closure', []),
        ];
    }

    private function scores(): array
    {
        return [
            'core_product_readiness' => (int) config('architecture-readiness.readiness_scores.core_product', 91),
            'production_readiness' => (int) config('architecture-readiness.readiness_scores.production', 88),
            'security_readiness' => (int) config('architecture-readiness.readiness_scores.security', 86),
            'abuse_protection_readiness' => (int) config('architecture-readiness.readiness_scores.abuse_protection', 87),
            'api_readiness' => (int) config('architecture-readiness.readiness_scores.api', 76),
            'automation_readiness' => (int) config('architecture-readiness.readiness_scores.automation', 70),
            'enterprise_readiness' => (int) config('architecture-readiness.readiness_scores.enterprise', 52),
            'governance_readiness' => (int) config('architecture-readiness.readiness_scores.governance', 60),
            'identity_readiness' => (int) config('architecture-readiness.readiness_scores.identity', 55),
            'provisioning_readiness' => (int) config('architecture-readiness.readiness_scores.provisioning', 47),
            'authorization_readiness' => (int) config('architecture-readiness.readiness_scores.authorization', 53),
            'audit_readiness' => (int) config('architecture-readiness.readiness_scores.audit', 56),
            'billing_readiness' => (int) config('architecture-readiness.readiness_scores.billing', 46),
            'multi_tenant_readiness' => (int) config('architecture-readiness.readiness_scores.multi_tenant', 34),
            'overall_architecture_readiness' => (int) config('architecture-readiness.readiness_scores.overall_architecture', 70),
        ];
    }

    private function architectureLayers(): array
    {
        return [
            'core_saas' => ['authentication', 'users', 'profiles', 'avatars', 'rbac', 'settings'],
            'temp_mail' => ['mailboxes', 'domains', 'messages', 'retention', 'cleanup', 'public_inbox'],
            'security' => ['rate_limiting', 'abuse_detection', 'audit_planning', 'identity_governance'],
            'api' => ['api_foundation', 'api_governance', 'developer_experience_planning'],
            'automation' => ['automation_planning', 'intelligence_planning'],
            'enterprise' => ['organization_planning', 'domain_models', 'ownership_models', 'governance_models', 'identity_models', 'provisioning_models', 'authorization_models', 'audit_models', 'billing_models'],
        ];
    }

    private function technicalDebt(): array
    {
        return [
            'low_priority' => ['documentation cross-links'],
            'medium_priority' => ['API lifecycle plan', 'dashboard phases'],
            'high_priority' => ['approve boundaries before tenancy', 'approve governance before enterprise billing'],
            'architectural_risks' => ['premature tenancy', 'authorization drift'],
            'operational_risks' => ['onboarding without evidence', 'billing without cost visibility'],
        ];
    }

    private function recommendations(): array
    {
        return [
            'launch' => ['separate closure from enterprise implementation'],
            'operations' => ['preserve queue-first operations'],
            'monitoring' => ['keep monitoring aggregate-first'],
            'security' => ['gate identity and tenant changes', 'require threat modeling'],
            'enterprise' => ['approve boundaries first', 'use separate release gates'],
        ];
    }
}
