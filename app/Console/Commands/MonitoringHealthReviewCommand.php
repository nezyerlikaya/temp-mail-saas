<?php

namespace App\Console\Commands;

use App\Services\Operations\MonitoringService;
use Illuminate\Console\Command;

class MonitoringHealthReviewCommand extends Command
{
    protected $signature = 'monitoring:health-review {--no-evaluate : Display current status without creating new alerts}';

    protected $description = 'Summarize privacy-safe monitoring status, alerts, and incidents.';

    public function handle(MonitoringService $monitoring): int
    {
        $review = $this->option('no-evaluate')
            ? ['status' => 'summary-only', 'alerts_created' => 0, 'incidents_created' => 0]
            : $monitoring->review();
        $summary = $monitoring->summary();

        $this->info('Monitoring health review');
        $this->line('Status: '.$review['status']);
        $this->line('Alerts created: '.$review['alerts_created']);
        $this->line('Incidents created: '.$review['incidents_created']);
        $this->line('Active alerts: '.$summary['active_alerts']);
        $this->line('Open incidents: '.$summary['open_incidents']);
        $this->line('Critical incidents: '.$summary['critical_incidents']);

        return $summary['critical_incidents'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
