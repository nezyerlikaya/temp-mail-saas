<?php

namespace App\Console\Commands;

use App\Services\Roadmap\EnterpriseAuthorizationGovernanceSpecificationService;
use Illuminate\Console\Command;

class SystemEnterpriseAuthorizationStatusCommand extends Command
{
    protected $signature = 'system:enterprise-authorization-status';

    protected $description = 'Display safe v1.1 enterprise authorization and permission governance specification status.';

    public function handle(EnterpriseAuthorizationGovernanceSpecificationService $specification): int
    {
        $report = $specification->report();
        $scores = $report['scores'];

        $this->info('v1.1 enterprise authorization governance summary');
        $this->line('Authorization Governance Readiness Score: '.$scores['authorization_governance_readiness']);
        $this->line('Permission Governance Readiness Score: '.$scores['permission_governance_readiness']);
        $this->line('Delegated Administration Readiness Score: '.$scores['delegated_administration_readiness']);
        $this->line('Privileged Access Governance Score: '.$scores['privileged_access_governance']);
        $this->line('Access Review Readiness Score: '.$scores['access_review_readiness']);
        $this->line('Separation of Duties Readiness Score: '.$scores['separation_of_duties_readiness']);
        $this->line('Authorization domains: '.count($report['authorization_domains']));
        $this->line('Permission lifecycle states: '.count($report['permission_lifecycle']));
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('Recommended STEP73: '.$report['step73']['recommended_next_phase']);

        return self::SUCCESS;
    }
}
