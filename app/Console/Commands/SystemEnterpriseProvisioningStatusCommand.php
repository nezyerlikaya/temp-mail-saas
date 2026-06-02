<?php

namespace App\Console\Commands;

use App\Services\Roadmap\EnterpriseProvisioningSpecificationService;
use Illuminate\Console\Command;

class SystemEnterpriseProvisioningStatusCommand extends Command
{
    protected $signature = 'system:enterprise-provisioning-status';

    protected $description = 'Display safe v1.1 enterprise provisioning and lifecycle specification status.';

    public function handle(EnterpriseProvisioningSpecificationService $specification): int
    {
        $report = $specification->report();
        $scores = $report['scores'];

        $this->info('v1.1 enterprise provisioning summary');
        $this->line('Provisioning Readiness Score: '.$scores['provisioning_readiness']);
        $this->line('Lifecycle Governance Readiness Score: '.$scores['lifecycle_governance_readiness']);
        $this->line('Joiner/Mover/Leaver Readiness Score: '.$scores['joiner_mover_leaver_readiness']);
        $this->line('Organization Onboarding Readiness Score: '.$scores['organization_onboarding_readiness']);
        $this->line('SCIM Readiness Score: '.$scores['scim_readiness']);
        $this->line('Lifecycle Audit Readiness Score: '.$scores['lifecycle_audit_readiness']);
        $this->line('Provisioning domains: '.count($report['provisioning_domains']));
        $this->line('Lifecycle audit events: '.count($report['lifecycle_audit_events']));
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('Recommended STEP72: '.$report['step72']['recommended_next_phase']);

        return self::SUCCESS;
    }
}
