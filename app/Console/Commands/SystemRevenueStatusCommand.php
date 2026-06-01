<?php

namespace App\Console\Commands;

use App\Services\Billing\RevenueReadinessService;
use Illuminate\Console\Command;

class SystemRevenueStatusCommand extends Command
{
    protected $signature = 'system:revenue-status';

    protected $description = 'Display safe revenue activation readiness.';

    public function handle(RevenueReadinessService $readiness): int
    {
        $report = $readiness->report();

        $this->info('Revenue readiness: '.strtoupper($report['status']));
        $this->line('Blockers: '.count($report['blockers']));
        $this->line('Warnings: '.count($report['warnings']));
        $this->line('Certification: '.strtoupper($report['certification']['status']));
        $this->line('Recommendations: '.count($report['recommendations']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($report[$key] as $item) {
                $this->line("{$label}: {$item['category']}.{$item['name']} - {$item['message']}");
            }
        }

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
