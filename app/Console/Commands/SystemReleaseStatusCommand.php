<?php

namespace App\Console\Commands;

use App\Services\System\ReleaseStatusService;
use Illuminate\Console\Command;

class SystemReleaseStatusCommand extends Command
{
    protected $signature = 'system:release-status';

    protected $description = 'Display a safe production release readiness summary.';

    public function handle(ReleaseStatusService $release): int
    {
        $status = $release->evaluate();

        $this->info('Release status: '.strtoupper($status['state']));
        $this->line('Target: '.$status['target']);
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
