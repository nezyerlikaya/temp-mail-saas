<?php

namespace App\Console\Commands;

use App\Services\ProductIntelligence\ProductIntelligenceService;
use Illuminate\Console\Command;

class SystemProductIntelligenceCommand extends Command
{
    protected $signature = 'system:product-intelligence';

    protected $description = 'Display safe first-party product intelligence.';

    public function handle(ProductIntelligenceService $intelligence): int
    {
        $report = $intelligence->report();

        $this->info('Product intelligence summary');
        $this->line('Feedback total: '.$report['feedback']['total']);
        $this->line('Open feedback: '.$report['feedback']['open']);
        $this->line('Trends: '.count($report['trends']));
        $this->line('Recurring issues: '.count($report['recurring_issues']));
        $this->line('Feature requests: '.count($report['feature_requests']));
        $this->line('Roadmap candidates: '.count($report['roadmap']['candidates']));
        $this->line('Recommendations: '.count($report['recommendations']));

        return self::SUCCESS;
    }
}
