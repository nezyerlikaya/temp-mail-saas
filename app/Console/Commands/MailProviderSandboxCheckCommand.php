<?php

namespace App\Console\Commands;

use App\Services\Mail\ProviderSandboxValidationService;
use Illuminate\Console\Command;

class MailProviderSandboxCheckCommand extends Command
{
    protected $signature = 'mail:provider-sandbox-check {--provider= : Provider to validate} {--fixture= : Fixture filename to validate} {--all : Validate all matching fixtures}';

    protected $description = 'Run safe provider sandbox validation without external HTTP calls.';

    public function handle(ProviderSandboxValidationService $sandbox): int
    {
        $provider = $this->option('provider') ? (string) $this->option('provider') : null;
        $fixture = $this->option('fixture') ? (string) $this->option('fixture') : null;
        $runAll = (bool) $this->option('all');

        $report = $sandbox->validate($provider, $fixture, $runAll);

        $this->info('Provider sandbox status: '.strtoupper($report['status']));
        $this->line('Passed: '.count($report['passed']));
        $this->line('Blockers: '.count($report['blockers']));

        foreach ($report['results'] as $result) {
            $this->line("{$result['provider']} {$result['fixture']}: {$result['status']} - {$result['message']}");
        }

        return $report['status'] === 'blocker' ? self::FAILURE : self::SUCCESS;
    }
}
