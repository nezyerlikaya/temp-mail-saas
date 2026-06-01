<?php

namespace App\Console\Commands;

use App\Services\Integrations\EcosystemCertificationService;
use Illuminate\Console\Command;

class SystemEcosystemStatusCommand extends Command
{
    protected $signature = 'system:ecosystem-status';

    protected $description = 'Display safe platform ecosystem readiness.';

    public function handle(EcosystemCertificationService $ecosystem): int
    {
        $report = $ecosystem->report();

        $this->info('Ecosystem readiness: '.strtoupper($report['status']));
        $this->line('Integration ecosystem: '.strtoupper($report['ecosystem']['state']));
        $this->line('Connectors: '.strtoupper($report['connectors']['status']));
        $this->line('Webhooks: '.strtoupper($report['webhooks']['status']));
        $this->line('Dependencies: '.strtoupper($report['dependencies']['status']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
