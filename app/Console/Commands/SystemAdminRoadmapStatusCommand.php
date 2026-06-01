<?php

namespace App\Console\Commands;

use App\Services\Roadmap\AdminRoadmapPlanningService;
use Illuminate\Console\Command;

class SystemAdminRoadmapStatusCommand extends Command
{
    protected $signature = 'system:admin-roadmap-status';

    protected $description = 'Display safe v1.1 admin and operations experience roadmap status.';

    public function handle(AdminRoadmapPlanningService $planning): int
    {
        $report = $planning->report();

        $this->info('v1.1 admin roadmap summary');
        $this->line('Admin workflow: '.strtoupper($report['reviews']['admin']['state']));
        $this->line('Operations workflow: '.strtoupper($report['reviews']['operations']['state']));
        $this->line('Dashboard usability: '.strtoupper($report['reviews']['dashboard']['state']));
        $this->line('Admin accessibility: '.strtoupper($report['reviews']['accessibility']['state']));
        $this->line('UX quick wins: '.count($report['ux_prioritization']['quick_wins']));
        $this->line('Operational bottlenecks: '.count($report['ux_prioritization']['operational_bottlenecks']));
        $this->line('Phase 1 candidates: '.count($report['roadmap']['phase_1']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return $report['summary']['state'] === 'improvement-needed' ? self::FAILURE : self::SUCCESS;
    }
}
