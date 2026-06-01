<?php

namespace App\Console\Commands;

use App\Services\Governance\GovernanceCertificationService;
use Illuminate\Console\Command;

class SystemGovernanceStatusCommand extends Command
{
    protected $signature = 'system:governance-status';

    protected $description = 'Display safe strategic platform governance readiness.';

    public function handle(GovernanceCertificationService $governance): int
    {
        $report = $governance->report();

        $this->info('Governance readiness: '.strtoupper($report['status']));
        $this->line('Governance state: '.strtoupper($report['governance']['state']));
        $this->line('Operational maturity: '.strtoupper($report['maturity']['status']));
        $this->line('Platform risks: '.strtoupper($report['risk']['status']));
        $this->line('Strategic operations: '.strtoupper($report['strategic_operations']['status']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
