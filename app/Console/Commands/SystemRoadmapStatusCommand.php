<?php

namespace App\Console\Commands;

use App\Services\Roadmap\V11RoadmapPlanningService;
use Illuminate\Console\Command;

class SystemRoadmapStatusCommand extends Command
{
    protected $signature = 'system:roadmap-status';

    protected $description = 'Display safe v1.1 roadmap and maintenance readiness.';

    public function handle(V11RoadmapPlanningService $roadmap): int
    {
        $report = $roadmap->report();

        $this->info('Roadmap readiness: '.strtoupper($report['status']));
        $this->line('Technical debt items: '.count($report['technical_debt']['items']));
        $this->line('Maintainability: '.strtoupper($report['maintainability']['status']));
        $this->line('Scalability: '.strtoupper($report['scalability']['status']));
        $this->line('v1.1 candidates: '.$report['priorities']['counts']['v1.1']);
        $this->line('v1.2 candidates: '.$report['priorities']['counts']['v1.2']);
        $this->line('Future backlog: '.$report['priorities']['counts']['future']);
        $this->line('Recommendations: '.count($report['recommendations']));

        foreach ($report['blockers'] as $item) {
            $this->line('Blocker: '.($item['key'] ?? $item['name']).' - '.($item['summary'] ?? $item['message']));
        }

        foreach ($report['warnings'] as $item) {
            $this->line('Warning: '.($item['key'] ?? $item['name']).' - '.($item['summary'] ?? $item['message']));
        }

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
