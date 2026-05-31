<?php

namespace App\Console\Commands;

use App\Services\System\FinalReleaseStatusService;
use Illuminate\Console\Command;

class SystemV1LaunchStatusCommand extends Command
{
    protected $signature = 'system:v1-launch-status';

    protected $description = 'Display safe v1.0.0 production launch certification status.';

    public function handle(FinalReleaseStatusService $release): int
    {
        $status = $release->evaluate();
        $certification = $status['certification'];

        $this->info('v1.0.0 launch status: '.strtoupper($status['status']));
        $this->line('Target: '.$certification['target']);
        $this->line('Decision: '.$status['launch_decision']);
        $this->line('Confidence: '.strtoupper($status['confidence']));
        $this->line('Certification: '.strtoupper($certification['status']));
        $this->line('Blockers: '.count($certification['blockers']));
        $this->line('Warnings: '.count($certification['warnings']));
        $this->line('Sign-off issues: '.(count($certification['sections']['sign_off']['blockers']) + count($certification['sections']['sign_off']['warnings'])));
        $this->line('Post-launch window: '.$status['post_launch']['window_hours'].' hours');
        $this->line('Rollback ready: '.($status['rollback']['ready'] ? 'yes' : 'no'));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($certification[$key] as $item) {
                $this->line("{$label}: ".($item['category'] ?? 'operations').'.'.$item['name'].' - '.$item['message']);
            }
        }

        return $status['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
