<?php

namespace App\Console\Commands;

use App\Services\Operations\First24HourMonitoringService;
use App\Services\Operations\LaunchMonitoringSummaryService;
use Illuminate\Console\Command;

class SystemLaunchMonitoringStatusCommand extends Command
{
    protected $signature = 'system:launch-monitoring-status';

    protected $description = 'Display safe first 24-hour production monitoring readiness.';

    public function handle(First24HourMonitoringService $monitoring, LaunchMonitoringSummaryService $summaries): int
    {
        $report = $monitoring->report();
        $summary = $summaries->summarize($report);

        $this->info('Launch monitoring status: '.strtoupper($summary['status']));
        $this->line('Critical indicators: '.$summary['critical_count']);
        $this->line('Warnings: '.$summary['warning_count']);
        $this->line('Incidents: '.strtoupper($summary['incident_status']));
        $this->line('Rollback review: '.strtoupper($summary['rollback_status']));
        $this->line('Recommendations: '.count($summary['recommendations']));

        foreach (['critical' => 'Critical', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($report[$key] as $item) {
                $this->line("{$label}: {$item['category']}.{$item['name']} - {$item['message']}");
            }
        }

        return $summary['status'] === 'critical' ? self::FAILURE : self::SUCCESS;
    }
}
