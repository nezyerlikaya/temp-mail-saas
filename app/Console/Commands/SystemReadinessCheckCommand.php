<?php

namespace App\Console\Commands;

use App\Services\System\ProductionReadinessService;
use Illuminate\Console\Command;

class SystemReadinessCheckCommand extends Command
{
    protected $signature = 'system:readiness-check';

    protected $description = 'Run production readiness checks and display a safe summary.';

    public function handle(ProductionReadinessService $readiness): int
    {
        $report = $readiness->report();

        $this->info('Production readiness summary');
        $this->line("Passed: {$report['passed']}");
        $this->line("Warnings: {$report['warnings']}");
        $this->line("Failures: {$report['failures']}");

        foreach ($report['checks'] as $check) {
            $this->line("{$check['name']}: {$check['status']}");
        }

        return $report['failures'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
