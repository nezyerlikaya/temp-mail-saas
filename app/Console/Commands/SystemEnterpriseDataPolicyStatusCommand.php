<?php

namespace App\Console\Commands;

use App\Services\Roadmap\EnterpriseDataOwnershipSpecificationService;
use Illuminate\Console\Command;

class SystemEnterpriseDataPolicyStatusCommand extends Command
{
    protected $signature = 'system:enterprise-data-policy-status';

    protected $description = 'Display safe v1.1 enterprise data ownership and access policy specification status.';

    public function handle(EnterpriseDataOwnershipSpecificationService $specification): int
    {
        $report = $specification->report();
        $scores = $report['scores'];

        $this->info('v1.1 enterprise data policy summary');
        $this->line('Data Ownership Readiness Score: '.$scores['data_ownership_readiness']);
        $this->line('Access Policy Readiness Score: '.$scores['access_policy_readiness']);
        $this->line('Resource Ownership Maturity Score: '.$scores['resource_ownership_maturity']);
        $this->line('Governance Readiness Score: '.$scores['governance_readiness']);
        $this->line('Compliance Readiness Score: '.$scores['compliance_readiness']);
        $this->line('Enterprise Audit Readiness Score: '.$scores['enterprise_audit_readiness']);
        $this->line('Resource ownership entries: '.count($report['resource_ownership_matrix']));
        $this->line('Access policy types: '.count($report['access_policies']));

        return self::SUCCESS;
    }
}
