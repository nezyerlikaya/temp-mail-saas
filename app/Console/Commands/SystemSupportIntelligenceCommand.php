<?php

namespace App\Console\Commands;

use App\Services\Support\CustomerSuccessIntelligenceService;
use Illuminate\Console\Command;

class SystemSupportIntelligenceCommand extends Command
{
    protected $signature = 'system:support-intelligence';

    protected $description = 'Display safe first-party support intelligence.';

    public function handle(CustomerSuccessIntelligenceService $intelligence): int
    {
        $report = $intelligence->report();

        $this->info('Support intelligence summary');
        $this->line('Customer health: '.strtoupper($report['health']['state']));
        $this->line('Open requests: '.$report['metrics']['open_requests']);
        $this->line('Recurring themes: '.count($report['recurring_themes']));
        $this->line('Onboarding issues: '.count($report['onboarding_issues']));
        $this->line('Retention risks: '.count($report['retention_risks']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return self::SUCCESS;
    }
}
