<?php

namespace App\Console\Commands;

use App\Services\Mail\ProviderActivationService;
use Illuminate\Console\Command;

class ProviderActivationStatusCommand extends Command
{
    protected $signature = 'provider:activation-status {--provider= : Provider to inspect}';

    protected $description = 'Display safe provider activation readiness status.';

    public function handle(ProviderActivationService $activation): int
    {
        $provider = $this->option('provider') ? (string) $this->option('provider') : null;
        $status = $activation->readiness($provider);
        $state = $status['blockers'] !== [] ? 'blocked' : ($status['warnings'] !== [] ? 'warning' : 'ready');

        $this->info('Provider activation status: '.strtoupper($state));
        foreach ($status['states'] as $name => $providerState) {
            $this->line("Provider {$name}: {$providerState}");
        }
        $this->line('Blockers: '.count($status['blockers']));
        $this->line('Warnings: '.count($status['warnings']));
        $this->line('Passed: '.count($status['passed']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($status[$key] as $item) {
                $this->line("{$label}: {$item['name']} - {$item['message']}");
            }
        }

        return $state === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
