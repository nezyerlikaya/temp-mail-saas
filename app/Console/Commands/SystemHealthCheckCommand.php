<?php

namespace App\Console\Commands;

use App\Services\System\SystemHealthService;
use Illuminate\Console\Command;

class SystemHealthCheckCommand extends Command
{
    protected $signature = 'system:health-check {--no-store : Run checks without storing health records}';

    protected $description = 'Run privacy-safe system health checks and store their aggregate results.';

    public function handle(SystemHealthService $health): int
    {
        $report = $health->run(store: ! $this->option('no-store'));

        $this->info("System health status: {$report['status']}");

        foreach ($report['checks'] as $check) {
            $this->line("{$check['check_name']}: {$check['status']}");
        }

        return self::SUCCESS;
    }
}
