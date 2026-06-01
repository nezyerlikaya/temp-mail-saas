<?php

namespace App\Console\Commands;

use App\Services\Seo\SeoGrowthReadinessService;
use Illuminate\Console\Command;

class SystemGrowthStatusCommand extends Command
{
    protected $signature = 'system:growth-status';

    protected $description = 'Display safe growth and SEO readiness.';

    public function handle(SeoGrowthReadinessService $readiness): int
    {
        $report = $readiness->report();

        $this->info('Growth readiness: '.strtoupper($report['status']));
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
