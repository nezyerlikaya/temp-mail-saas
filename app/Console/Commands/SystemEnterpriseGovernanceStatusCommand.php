<?php

namespace App\Console\Commands;

use App\Services\Roadmap\EnterpriseGovernanceSpecificationService;
use Illuminate\Console\Command;

class SystemEnterpriseGovernanceStatusCommand extends Command
{
    protected $signature = 'system:enterprise-governance-status';

    protected $description = 'Display safe v1.1 enterprise governance and compliance specification status.';

    public function handle(EnterpriseGovernanceSpecificationService $specification): int
    {
        $report = $specification->report();
        $scores = $report['scores'];

        $this->info('v1.1 enterprise governance summary');
        $this->line('Governance Readiness Score: '.$scores['governance_readiness']);
        $this->line('Compliance Readiness Score: '.$scores['compliance_readiness']);
        $this->line('Policy Management Readiness Score: '.$scores['policy_management_readiness']);
        $this->line('Access Review Readiness Score: '.$scores['access_review_readiness']);
        $this->line('Audit Readiness Score: '.$scores['audit_readiness']);
        $this->line('Incident Governance Readiness Score: '.$scores['incident_governance_readiness']);
        $this->line('Governance domains: '.count($report['governance_domains']));
        $this->line('Policy types: '.count($report['policies']));
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('Recommended STEP70: '.$report['step70']['recommended_next_phase']);

        return self::SUCCESS;
    }
}
