<?php

namespace App\Console\Commands;

use App\Services\Enterprise\EnterpriseCertificationService;
use Illuminate\Console\Command;

class SystemEnterpriseStatusCommand extends Command
{
    protected $signature = 'system:enterprise-status';

    protected $description = 'Display safe enterprise account readiness.';

    public function handle(EnterpriseCertificationService $enterprise): int
    {
        $report = $enterprise->report();

        $this->info('Enterprise readiness: '.strtoupper($report['status']));
        $this->line('Account health: '.strtoupper($report['account_health']['state']));
        $this->line('Governance: '.strtoupper($report['governance']['status']));
        $this->line('Lifecycle: '.strtoupper($report['lifecycle']['status']));
        $this->line('Membership intelligence: '.strtoupper($report['membership']['status']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
