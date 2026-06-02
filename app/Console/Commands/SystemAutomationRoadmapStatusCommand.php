<?php

namespace App\Console\Commands;

use App\Services\Roadmap\AutomationRoadmapPlanningService;
use Illuminate\Console\Command;

class SystemAutomationRoadmapStatusCommand extends Command
{
    protected $signature = 'system:automation-roadmap-status';

    protected $description = 'Display safe v1.1 automation and intelligence roadmap status.';

    public function handle(AutomationRoadmapPlanningService $planning): int
    {
        $report = $planning->report();

        $this->info('v1.1 automation roadmap summary');
        $this->line('Automation capability: '.strtoupper($report['reviews']['automation']['state']));
        $this->line('Intelligence capability: '.strtoupper($report['reviews']['intelligence']['state']));
        $this->line('Automation lifecycle: '.strtoupper($report['reviews']['lifecycle']['state']));
        $this->line('Operational intelligence: '.strtoupper($report['reviews']['operations']['state']));
        $this->line('Enhancement quick wins: '.count($report['enhancement_prioritization']['quick_wins']));
        $this->line('High-impact enhancements: '.count($report['enhancement_prioritization']['high_impact_enhancements']));
        $this->line('Phase 1 candidates: '.count($report['roadmap']['phase_1']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return $report['summary']['state'] === 'improvement-needed' ? self::FAILURE : self::SUCCESS;
    }
}
