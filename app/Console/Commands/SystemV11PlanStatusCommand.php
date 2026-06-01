<?php

namespace App\Console\Commands;

use App\Services\Roadmap\V11ReleasePlanningService;
use Illuminate\Console\Command;

class SystemV11PlanStatusCommand extends Command
{
    protected $signature = 'system:v11-plan-status';

    protected $description = 'Display safe v1.1 feature candidate planning status.';

    public function handle(V11ReleasePlanningService $planning): int
    {
        $report = $planning->report();

        $this->info('v1.1 release planning summary');
        $this->line('Candidates: '.$report['candidate_summary']['total']);
        $this->line('Accepted: '.$report['candidate_summary']['accepted']);
        $this->line('Deferred: '.$report['candidate_summary']['deferred']);
        $this->line('Implementation readiness: '.strtoupper($report['implementation_readiness']['status']));
        $this->line('Quick wins: '.count($report['quick_wins']));
        $this->line('High-risk items: '.count($report['high_risk_items']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return $report['implementation_readiness']['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
