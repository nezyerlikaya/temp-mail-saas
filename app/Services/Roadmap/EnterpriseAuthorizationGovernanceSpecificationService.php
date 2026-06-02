<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class EnterpriseAuthorizationGovernanceSpecificationService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'authorization_domains' => config('enterprise-authorization.authorization_domains', []),
            'permission_types' => config('enterprise-authorization.permission_types', []),
            'permission_lifecycle' => config('enterprise-authorization.permission_lifecycle', []),
            'access_boundaries' => config('enterprise-authorization.access_boundaries', []),
            'delegated_roles' => config('enterprise-authorization.delegated_roles', []),
            'privileged_access' => config('enterprise-authorization.privileged_access', []),
            'certification' => config('enterprise-authorization.certification', []),
            'separation_of_duties' => config('enterprise-authorization.separation_of_duties', []),
            'authorization_risks' => config('enterprise-authorization.authorization_risks', []),
            'access_reviews' => config('enterprise-authorization.access_reviews', []),
            'service_authorization' => config('enterprise-authorization.service_authorization', []),
            'audit_events' => config('enterprise-authorization.audit_events', []),
            'policy_enforcement_readiness' => config('enterprise-authorization.policy_enforcement_readiness', []),
            'critical_gaps' => config('enterprise-authorization.critical_gaps', []),
            'step73' => config('enterprise-authorization.step73', []),
        ];
    }

    private function scores(): array
    {
        return [
            'authorization_governance_readiness' => (int) config('enterprise-authorization.readiness_scores.authorization_governance', 53),
            'permission_governance_readiness' => (int) config('enterprise-authorization.readiness_scores.permission_governance', 49),
            'delegated_administration_readiness' => (int) config('enterprise-authorization.readiness_scores.delegated_administration', 50),
            'privileged_access_governance' => (int) config('enterprise-authorization.readiness_scores.privileged_access', 46),
            'access_review_readiness' => (int) config('enterprise-authorization.readiness_scores.access_review', 48),
            'separation_of_duties_readiness' => (int) config('enterprise-authorization.readiness_scores.separation_of_duties', 44),
        ];
    }
}
