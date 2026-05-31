<?php

namespace App\Console\Commands;

use App\Services\System\StagingReadinessService;
use Illuminate\Console\Command;

class SystemStagingReadinessCommand extends Command
{
    protected $signature = 'system:staging-readiness';

    protected $description = 'Display a safe provider staging readiness summary.';

    public function handle(StagingReadinessService $staging): int
    {
        $status = $staging->evaluate();

        $this->info('Staging readiness: '.strtoupper($status['state']));
        $this->line($status['summary']);
        $this->line('Blockers: '.count($status['blockers']));
        $this->line('Warnings: '.count($status['warnings']));
        $this->line('Recommendations: '.count($status['recommendations']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning', 'recommendations' => 'Recommendation'] as $key => $label) {
            foreach ($status[$key] as $item) {
                $this->line("{$label}: {$item['name']} - {$item['message']}");
            }
        }

        return $status['state'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
