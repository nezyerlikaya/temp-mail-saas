<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class EnterpriseDomainModelSpecificationService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'domains' => config('enterprise-domain-model.domains', []),
            'role_model' => config('enterprise-domain-model.role_model', []),
            'billing_domain' => config('enterprise-domain-model.billing_domain', []),
            'governance_domain' => config('enterprise-domain-model.governance_domain', []),
            'security_domain' => config('enterprise-domain-model.security_domain', []),
            'tenant_boundaries' => config('enterprise-domain-model.tenant_boundaries', []),
            'resource_ownership' => config('enterprise-domain-model.resource_ownership', []),
            'domain_events' => config('enterprise-domain-model.domain_events', []),
            'future_api_domains' => config('enterprise-domain-model.future_api_domains', []),
            'critical_gaps' => config('enterprise-domain-model.critical_gaps', []),
            'step68' => config('enterprise-domain-model.step68', []),
        ];
    }

    private function scores(): array
    {
        return [
            'organization_domain_maturity' => (int) config('enterprise-domain-model.maturity_scores.organization_domain', 58),
            'workspace_domain_maturity' => (int) config('enterprise-domain-model.maturity_scores.workspace_domain', 45),
            'membership_domain_maturity' => (int) config('enterprise-domain-model.maturity_scores.membership_domain', 52),
            'governance_domain_maturity' => (int) config('enterprise-domain-model.maturity_scores.governance_domain', 50),
            'enterprise_security_domain' => (int) config('enterprise-domain-model.maturity_scores.enterprise_security_domain', 55),
            'tenant_boundary_readiness' => (int) config('enterprise-domain-model.maturity_scores.tenant_boundary', 40),
        ];
    }
}
