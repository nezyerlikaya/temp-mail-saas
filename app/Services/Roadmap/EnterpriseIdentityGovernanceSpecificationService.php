<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class EnterpriseIdentityGovernanceSpecificationService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'identity_sources' => config('enterprise-identity-governance.identity_sources', []),
            'sso' => config('enterprise-identity-governance.sso', []),
            'federation' => config('enterprise-identity-governance.federation', []),
            'identity_lifecycle' => config('enterprise-identity-governance.identity_lifecycle', []),
            'access_governance' => config('enterprise-identity-governance.access_governance', []),
            'mfa_governance' => config('enterprise-identity-governance.mfa_governance', []),
            'session_governance' => config('enterprise-identity-governance.session_governance', []),
            'device_governance' => config('enterprise-identity-governance.device_governance', []),
            'identity_risks' => config('enterprise-identity-governance.identity_risks', []),
            'role_governance' => config('enterprise-identity-governance.role_governance', []),
            'audit_events' => config('enterprise-identity-governance.audit_events', []),
            'provisioning' => config('enterprise-identity-governance.provisioning', []),
            'critical_gaps' => config('enterprise-identity-governance.critical_gaps', []),
            'step71' => config('enterprise-identity-governance.step71', []),
        ];
    }

    private function scores(): array
    {
        return [
            'identity_governance_readiness' => (int) config('enterprise-identity-governance.readiness_scores.identity_governance', 52),
            'enterprise_sso_readiness' => (int) config('enterprise-identity-governance.readiness_scores.enterprise_sso', 44),
            'federation_readiness' => (int) config('enterprise-identity-governance.readiness_scores.federation', 46),
            'mfa_governance_readiness' => (int) config('enterprise-identity-governance.readiness_scores.mfa_governance', 48),
            'session_governance_readiness' => (int) config('enterprise-identity-governance.readiness_scores.session_governance', 50),
            'device_governance_readiness' => (int) config('enterprise-identity-governance.readiness_scores.device_governance', 42),
        ];
    }
}
