<?php

namespace App\Console\Commands;

use App\Models\DomainHealthCheck;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use Illuminate\Console\Command;

class OperationsHealthSummaryCommand extends Command
{
    protected $signature = 'operations:health-summary';

    protected $description = 'Display a safe operational summary for dashboard-ready data.';

    public function handle(): int
    {
        $this->info('Operations health summary');
        $this->line('Events: '.OperationsEvent::query()->count());
        $this->line('Queue metrics: '.QueueMetric::query()->count());
        $this->line('Domain checks: '.DomainHealthCheck::query()->count());

        return self::SUCCESS;
    }
}
