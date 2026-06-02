<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class EnterpriseProvisioningSpecificationService extends Service
{
    public function report(): array
    {
        return [
            'scores' => $this->scores(),
            'provisioning_domains' => config('enterprise-provisioning.provisioning_domains', []),
            'joiner_mover_leaver' => config('enterprise-provisioning.joiner_mover_leaver', []),
            'access_lifecycle' => config('enterprise-provisioning.access_lifecycle', []),
            'organization_onboarding' => config('enterprise-provisioning.organization_onboarding', []),
            'organization_offboarding' => config('enterprise-provisioning.organization_offboarding', []),
            'workspace_lifecycle' => config('enterprise-provisioning.workspace_lifecycle', []),
            'team_lifecycle' => config('enterprise-provisioning.team_lifecycle', []),
            'membership_governance' => config('enterprise-provisioning.membership_governance', []),
            'scim_readiness' => config('enterprise-provisioning.scim_readiness', []),
            'provisioning_risks' => config('enterprise-provisioning.provisioning_risks', []),
            'lifecycle_audit_events' => config('enterprise-provisioning.lifecycle_audit_events', []),
            'automation_readiness' => config('enterprise-provisioning.automation_readiness', []),
            'critical_gaps' => config('enterprise-provisioning.critical_gaps', []),
            'step72' => config('enterprise-provisioning.step72', []),
        ];
    }

    private function scores(): array
    {
        return [
            'provisioning_readiness' => (int) config('enterprise-provisioning.readiness_scores.provisioning', 45),
            'lifecycle_governance_readiness' => (int) config('enterprise-provisioning.readiness_scores.lifecycle_governance', 50),
            'joiner_mover_leaver_readiness' => (int) config('enterprise-provisioning.readiness_scores.joiner_mover_leaver', 48),
            'organization_onboarding_readiness' => (int) config('enterprise-provisioning.readiness_scores.organization_onboarding', 52),
            'scim_readiness' => (int) config('enterprise-provisioning.readiness_scores.scim', 38),
            'lifecycle_audit_readiness' => (int) config('enterprise-provisioning.readiness_scores.lifecycle_audit', 55),
        ];
    }
}
