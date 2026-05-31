<?php

namespace App\Console\Commands;

use App\Services\System\RC3CertificationService;
use Illuminate\Console\Command;

class SystemRC3CertificationCommand extends Command
{
    protected $signature = 'system:rc3-certification';

    protected $description = 'Display a safe RC3 launch certification summary.';

    public function handle(RC3CertificationService $certification): int
    {
        $report = $certification->report();

        $this->info('RC3 certification: '.strtoupper($report['status']));
        $this->line('Target: '.$report['target']);
        $this->line($report['summary']);
        $this->line('Blockers: '.count($report['blockers']));
        $this->line('Warnings: '.count($report['warnings']));
        $this->line('Recommendations: '.count($report['recommendations']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($report[$key] as $item) {
                $this->line("{$label}: {$item['category']}.{$item['name']} - {$item['message']}");
            }
        }

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
