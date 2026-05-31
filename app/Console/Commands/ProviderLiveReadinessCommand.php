<?php

namespace App\Console\Commands;

use App\Services\Mail\LiveProviderReadinessService;
use Illuminate\Console\Command;

class ProviderLiveReadinessCommand extends Command
{
    protected $signature = 'provider:live-readiness {--provider= : Provider to inspect}';

    protected $description = 'Display safe live provider activation readiness status.';

    public function handle(LiveProviderReadinessService $readiness): int
    {
        $report = $readiness->report($this->option('provider') ? (string) $this->option('provider') : null);

        $this->info('Live provider readiness: '.strtoupper($report['status']));
        $this->line('Providers: '.implode(', ', $report['providers']));
        $this->line('Blockers: '.count($report['blockers']));
        $this->line('Warnings: '.count($report['warnings']));
        $this->line('Rollback blockers: '.count($report['rollback']['blockers']));
        $this->line('Rollback warnings: '.count($report['rollback']['warnings']));
        $this->line('Recommendations: '.count($report['recommendations']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($report[$key] as $item) {
                $this->line("{$label}: {$item['category']}.{$item['name']} - {$item['message']}");
            }
        }

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
