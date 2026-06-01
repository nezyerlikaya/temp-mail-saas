<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsReadinessService;
use Illuminate\Console\Command;

class SystemAnalyticsStatusCommand extends Command
{
    protected $signature = 'system:analytics-status';

    protected $description = 'Display safe product analytics readiness.';

    public function handle(AnalyticsReadinessService $readiness): int
    {
        $report = $readiness->report();

        $this->info('Analytics readiness: '.strtoupper($report['status']));
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
