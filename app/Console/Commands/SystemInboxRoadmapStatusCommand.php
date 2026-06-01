<?php

namespace App\Console\Commands;

use App\Services\Roadmap\InboxRoadmapPlanningService;
use Illuminate\Console\Command;

class SystemInboxRoadmapStatusCommand extends Command
{
    protected $signature = 'system:inbox-roadmap-status';

    protected $description = 'Display safe v1.1 inbox and mailbox experience roadmap status.';

    public function handle(InboxRoadmapPlanningService $planning): int
    {
        $report = $planning->report();

        $this->info('v1.1 inbox roadmap summary');
        $this->line('Inbox experience: '.strtoupper($report['reviews']['inbox']['state']));
        $this->line('Mailbox lifecycle: '.strtoupper($report['reviews']['mailbox']['state']));
        $this->line('Message workflow: '.strtoupper($report['reviews']['message_workflow']['state']));
        $this->line('Accessibility: '.strtoupper($report['reviews']['accessibility']['state']));
        $this->line('UX quick wins: '.count($report['ux_prioritization']['quick_wins']));
        $this->line('Phase 1 candidates: '.count($report['roadmap']['phase_1']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return $report['summary']['state'] === 'improvement-needed' ? self::FAILURE : self::SUCCESS;
    }
}
