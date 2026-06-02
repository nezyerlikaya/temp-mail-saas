<?php

namespace App\Console\Commands;

use App\Services\Roadmap\EnterpriseIdentityGovernanceSpecificationService;
use Illuminate\Console\Command;

class SystemEnterpriseIdentityStatusCommand extends Command
{
    protected $signature = 'system:enterprise-identity-status';

    protected $description = 'Display safe v1.1 enterprise SSO and identity governance specification status.';

    public function handle(EnterpriseIdentityGovernanceSpecificationService $specification): int
    {
        $report = $specification->report();
        $scores = $report['scores'];

        $this->info('v1.1 enterprise identity governance summary');
        $this->line('Identity Governance Readiness Score: '.$scores['identity_governance_readiness']);
        $this->line('Enterprise SSO Readiness Score: '.$scores['enterprise_sso_readiness']);
        $this->line('Federation Readiness Score: '.$scores['federation_readiness']);
        $this->line('MFA Governance Readiness Score: '.$scores['mfa_governance_readiness']);
        $this->line('Session Governance Readiness Score: '.$scores['session_governance_readiness']);
        $this->line('Device Governance Readiness Score: '.$scores['device_governance_readiness']);
        $this->line('Identity sources: '.count($report['identity_sources']));
        $this->line('SSO protocols: '.count($report['sso']['protocols']));
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('Recommended STEP71: '.$report['step71']['recommended_next_phase']);

        return self::SUCCESS;
    }
}
