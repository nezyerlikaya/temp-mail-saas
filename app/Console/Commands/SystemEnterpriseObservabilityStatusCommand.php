<?php

namespace App\Console\Commands;

use App\Services\Roadmap\EnterpriseObservabilityGovernanceSpecificationService;
use Illuminate\Console\Command;

class SystemEnterpriseObservabilityStatusCommand extends Command
{
    protected $signature = 'system:enterprise-observability-status';

    protected $description = 'Display safe v1.1 enterprise audit, observability, and operational governance specification status.';

    public function handle(EnterpriseObservabilityGovernanceSpecificationService $specification): int
    {
        $report = $specification->report();
        $scores = $report['scores'];

        $this->info('v1.1 enterprise observability governance summary');
        $this->line('Audit Governance Readiness Score: '.$scores['audit_governance_readiness']);
        $this->line('Observability Readiness Score: '.$scores['observability_readiness']);
        $this->line('Operational Governance Readiness Score: '.$scores['operational_governance_readiness']);
        $this->line('Compliance Evidence Readiness Score: '.$scores['compliance_evidence_readiness']);
        $this->line('Monitoring Governance Readiness Score: '.$scores['monitoring_governance_readiness']);
        $this->line('SIEM Readiness Score: '.$scores['siem_readiness']);
        $this->line('Audit domains: '.count($report['audit_governance']));
        $this->line('Monitoring domains: '.count($report['monitoring_governance']));
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('Recommended STEP74: '.$report['step74']['recommended_next_phase']);

        return self::SUCCESS;
    }
}
