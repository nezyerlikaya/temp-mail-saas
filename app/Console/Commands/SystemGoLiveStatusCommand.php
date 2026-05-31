<?php

namespace App\Console\Commands;

use App\Services\System\GoLiveStatusService;
use Illuminate\Console\Command;

class SystemGoLiveStatusCommand extends Command
{
    protected $signature = 'system:go-live-status';

    protected $description = 'Display a safe production go-live readiness summary.';

    public function handle(GoLiveStatusService $goLive): int
    {
        $status = $goLive->evaluate();

        $this->info('Go-live status: '.strtoupper($status['state']));
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
