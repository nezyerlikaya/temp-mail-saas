<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class EnterpriseDataOwnershipSpecificationService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'ownership_domains' => config('enterprise-data-policy.ownership_domains', []),
            'resource_ownership_matrix' => config('enterprise-data-policy.resource_ownership_matrix', []),
            'access_policies' => config('enterprise-data-policy.access_policies', []),
            'boundaries' => config('enterprise-data-policy.boundaries', []),
            'visibility_roles' => config('enterprise-data-policy.visibility_roles', []),
            'governance' => config('enterprise-data-policy.governance', []),
            'compliance' => config('enterprise-data-policy.compliance', []),
            'resource_isolation' => config('enterprise-data-policy.resource_isolation', []),
            'delegated_access_roles' => config('enterprise-data-policy.delegated_access_roles', []),
            'audit_access' => config('enterprise-data-policy.audit_access', []),
            'data_exports' => config('enterprise-data-policy.data_exports', []),
            'api_ownership' => config('enterprise-data-policy.api_ownership', []),
        ];
    }

    private function scores(): array
    {
        return [
            'data_ownership_readiness' => (int) config('enterprise-data-policy.readiness_scores.data_ownership', 54),
            'access_policy_readiness' => (int) config('enterprise-data-policy.readiness_scores.access_policy', 50),
            'resource_ownership_maturity' => (int) config('enterprise-data-policy.readiness_scores.resource_ownership', 52),
            'governance_readiness' => (int) config('enterprise-data-policy.readiness_scores.governance', 56),
            'compliance_readiness' => (int) config('enterprise-data-policy.readiness_scores.compliance', 46),
            'enterprise_audit_readiness' => (int) config('enterprise-data-policy.readiness_scores.enterprise_audit', 58),
        ];
    }
}
