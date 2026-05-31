<?php

namespace App\Console\Commands;

use App\Services\Operations\SystemMetricsService;
use Illuminate\Console\Command;

class OperationsCollectMetricsCommand extends Command
{
    protected $signature = 'operations:collect-metrics {--no-store : Collect metrics without writing metric rows}';

    protected $description = 'Collect privacy-safe operational metrics and generate threshold events.';

    public function handle(SystemMetricsService $metrics): int
    {
        $report = $metrics->collect(store: ! $this->option('no-store'));

        $this->info('Operations metrics collected.');
        $this->line('Queue metrics: '.count($report['queue']));
        $this->line('Domain checks: '.count($report['domains']));
        $this->line('Failed jobs: '.$report['failed_jobs']['total_failed_jobs']);

        return self::SUCCESS;
    }
}
