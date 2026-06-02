<?php

namespace App\Console\Commands;

use App\Services\Roadmap\EnterpriseDomainModelSpecificationService;
use Illuminate\Console\Command;

class SystemEnterpriseDomainStatusCommand extends Command
{
    protected $signature = 'system:enterprise-domain-status';

    protected $description = 'Display safe v1.1 enterprise domain model specification status.';

    public function handle(EnterpriseDomainModelSpecificationService $specification): int
    {
        $report = $specification->report();
        $scores = $report['scores'];

        $this->info('v1.1 enterprise domain model summary');
        $this->line('Organization Domain Maturity Score: '.$scores['organization_domain_maturity']);
        $this->line('Workspace Domain Maturity Score: '.$scores['workspace_domain_maturity']);
        $this->line('Membership Domain Maturity Score: '.$scores['membership_domain_maturity']);
        $this->line('Governance Domain Maturity Score: '.$scores['governance_domain_maturity']);
        $this->line('Enterprise Security Domain Score: '.$scores['enterprise_security_domain']);
        $this->line('Tenant Boundary Readiness Score: '.$scores['tenant_boundary_readiness']);
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('Recommended STEP68: '.$report['step68']['recommended_next_phase']);

        return self::SUCCESS;
    }
}
