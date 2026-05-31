<?php

namespace App\Console\Commands;

use App\Services\System\ProductionLoadValidationService;
use App\Services\System\StressReadinessService;
use Illuminate\Console\Command;

class SystemLoadReadinessCommand extends Command
{
    protected $signature = 'system:load-readiness';

    protected $description = 'Display safe production load and stress readiness summaries.';

    public function handle(ProductionLoadValidationService $load, StressReadinessService $stress): int
    {
        $loadReport = $load->report();
        $stressReport = $stress->report();
        $blockers = count($loadReport['blockers']) + count($stressReport['blockers']);
        $warnings = count($loadReport['warnings']) + count($stressReport['warnings']);
        $recommendations = count($loadReport['recommendations']) + count($stressReport['recommendations']);
        $state = $blockers > 0 ? 'blocked' : ($warnings > 0 ? 'warning' : 'ready');

        $this->info('Load readiness: '.strtoupper($state));
        $this->line('Load status: '.strtoupper($loadReport['status']));
        $this->line('Stress status: '.strtoupper($stressReport['status']));
        $this->line('Blockers: '.$blockers);
        $this->line('Warnings: '.$warnings);
        $this->line('Recommendations: '.$recommendations);
        $this->line('Scenarios: '.(int) ($stressReport['scenarios']['scenario_count'] ?? 0));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($loadReport[$key] as $item) {
                $this->line("{$label}: load.{$item['name']} - {$item['message']}");
            }

            foreach ($stressReport[$key] as $item) {
                $this->line("{$label}: stress.{$item['name']} - {$item['message']}");
            }
        }

        return $state === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
