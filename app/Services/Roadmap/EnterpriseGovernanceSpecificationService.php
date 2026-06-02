<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class EnterpriseGovernanceSpecificationService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'governance_domains' => config('enterprise-governance.governance_domains', []),
            'compliance_readiness' => config('enterprise-governance.compliance_readiness', []),
            'policies' => config('enterprise-governance.policies', []),
            'access_reviews' => config('enterprise-governance.access_reviews', []),
            'risk_categories' => config('enterprise-governance.risk_categories', []),
            'audit_events' => config('enterprise-governance.audit_events', []),
            'audit_fields' => config('enterprise-governance.audit_fields', []),
            'incident_governance' => config('enterprise-governance.incident_governance', []),
            'retention_governance' => config('enterprise-governance.retention_governance', []),
            'dashboard_planning' => config('enterprise-governance.dashboard_planning', []),
            'critical_gaps' => config('enterprise-governance.critical_gaps', []),
            'step70' => config('enterprise-governance.step70', []),
        ];
    }

    private function scores(): array
    {
        return [
            'governance_readiness' => (int) config('enterprise-governance.readiness_scores.governance', 57),
            'compliance_readiness' => (int) config('enterprise-governance.readiness_scores.compliance', 49),
            'policy_management_readiness' => (int) config('enterprise-governance.readiness_scores.policy_management', 51),
            'access_review_readiness' => (int) config('enterprise-governance.readiness_scores.access_review', 47),
            'audit_readiness' => (int) config('enterprise-governance.readiness_scores.audit', 60),
            'incident_governance_readiness' => (int) config('enterprise-governance.readiness_scores.incident_governance', 53),
        ];
    }
}
