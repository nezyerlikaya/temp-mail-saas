<?php

namespace App\Console\Commands;

use App\Services\Roadmap\ApiRoadmapPlanningService;
use Illuminate\Console\Command;

class SystemApiRoadmapStatusCommand extends Command
{
    protected $signature = 'system:api-roadmap-status';

    protected $description = 'Display safe v1.1 API and developer experience roadmap status.';

    public function handle(ApiRoadmapPlanningService $planning): int
    {
        $report = $planning->report();

        $this->info('v1.1 API roadmap summary');
        $this->line('API usability: '.strtoupper($report['reviews']['api']['state']));
        $this->line('API lifecycle: '.strtoupper($report['reviews']['lifecycle']['state']));
        $this->line('Developer onboarding: '.strtoupper($report['reviews']['onboarding']['state']));
        $this->line('API documentation: '.strtoupper($report['reviews']['documentation']['state']));
        $this->line('DX quick wins: '.count($report['dx_prioritization']['quick_wins']));
        $this->line('Onboarding improvements: '.count($report['dx_prioritization']['onboarding_improvements']));
        $this->line('Phase 1 candidates: '.count($report['roadmap']['phase_1']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return $report['summary']['state'] === 'improvement-needed' ? self::FAILURE : self::SUCCESS;
    }
}
