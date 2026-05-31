<?php

namespace App\Console\Commands;

use App\Services\System\PublicBetaCertificationService;
use Illuminate\Console\Command;

class SystemPublicBetaStatusCommand extends Command
{
    protected $signature = 'system:public-beta-status';

    protected $description = 'Display safe public beta readiness and certification status.';

    public function handle(PublicBetaCertificationService $certification): int
    {
        $report = $certification->report();

        $this->info('Public beta status: '.strtoupper($report['status']));
        $this->line('Target: '.$report['target']);
        $this->line($report['summary']);
        $this->line('Beta readiness: '.strtoupper($report['readiness']['status']));
        $this->line('RC3 certification: '.strtoupper($report['rc3']['status']));
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
