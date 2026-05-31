<?php

namespace App\Console\Commands;

use App\Services\Domain\LiveDomainReadinessService;
use Illuminate\Console\Command;

class DomainLiveReadinessCommand extends Command
{
    protected $signature = 'domain:live-readiness {--domain= : Domain to inspect}';

    protected $description = 'Display safe live domain activation readiness status.';

    public function handle(LiveDomainReadinessService $readiness): int
    {
        $report = $readiness->report($this->option('domain') ? (string) $this->option('domain') : null);

        $this->info('Live domain readiness: '.strtoupper($report['status']));
        $this->line('Domains reviewed: '.$report['domain_count']);
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
