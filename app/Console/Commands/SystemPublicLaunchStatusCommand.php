<?php

namespace App\Console\Commands;

use App\Services\System\PublicLaunchReadinessService;
use Illuminate\Console\Command;

class SystemPublicLaunchStatusCommand extends Command
{
    protected $signature = 'system:public-launch-status';

    protected $description = 'Display safe public production launch readiness.';

    public function handle(PublicLaunchReadinessService $readiness): int
    {
        $report = $readiness->report();

        $this->info('Public launch status: '.strtoupper($report['status']));
        $this->line('Blockers: '.count($report['blockers']));
        $this->line('Warnings: '.count($report['warnings']));
        $this->line('Certification: '.strtoupper($report['certification']['status']));
        $this->line('Launch gates: '.strtoupper($report['gates']['status']));
        $this->line('Observation window: '.$report['observation']['window_days'].' days');
        $this->line('Recommendations: '.count($report['recommendations']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($report[$key] as $item) {
                $this->line("{$label}: {$item['category']}.{$item['name']} - {$item['message']}");
            }
        }

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
