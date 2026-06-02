<?php

namespace App\Console\Commands;

use App\Services\Roadmap\EnterpriseBillingGovernanceSpecificationService;
use Illuminate\Console\Command;

class SystemEnterpriseBillingStatusCommand extends Command
{
    protected $signature = 'system:enterprise-billing-status';

    protected $description = 'Display safe v1.1 enterprise billing ownership and seat governance specification status.';

    public function handle(EnterpriseBillingGovernanceSpecificationService $specification): int
    {
        $report = $specification->report();
        $scores = $report['scores'];

        $this->info('v1.1 enterprise billing governance summary');
        $this->line('Enterprise Billing Readiness Score: '.$scores['enterprise_billing_readiness']);
        $this->line('Seat Governance Readiness Score: '.$scores['seat_governance_readiness']);
        $this->line('Subscription Governance Readiness Score: '.$scores['subscription_governance_readiness']);
        $this->line('Ownership Governance Readiness Score: '.$scores['ownership_governance_readiness']);
        $this->line('Cost Governance Readiness Score: '.$scores['cost_governance_readiness']);
        $this->line('Financial Governance Readiness Score: '.$scores['financial_governance_readiness']);
        $this->line('Billing domains: '.count($report['billing_domains']));
        $this->line('Seat lifecycle states: '.count($report['seat_lifecycle']));
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('Recommended STEP75: '.$report['step75']['recommended_next_phase']);

        return self::SUCCESS;
    }
}
