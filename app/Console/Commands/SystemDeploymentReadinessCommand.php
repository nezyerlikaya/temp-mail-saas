<?php

namespace App\Console\Commands;

use App\Services\System\ProductionDeploymentReadinessService;
use Illuminate\Console\Command;

class SystemDeploymentReadinessCommand extends Command
{
    protected $signature = 'system:deployment-readiness';

    protected $description = 'Display safe production deployment readiness status.';

    public function handle(ProductionDeploymentReadinessService $readiness): int
    {
        $report = $readiness->report();

        $this->info('Production deployment readiness: '.strtoupper($report['status']));
        $this->line('Blockers: '.count($report['blockers']));
        $this->line('Warnings: '.count($report['warnings']));
        $this->line('Recommendations: '.count($report['recommendations']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($report[$key] as $item) {
                $this->line("{$label}: {$item['category']}.{$item['name']} - {$item['message']}");
            }
        }

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
