<?php

namespace App\Console\Commands;

use App\Services\System\FirstLiveSmokeTestService;
use App\Services\System\ProductionEnvironmentValidationService;
use App\Services\System\ServerReadinessService;
use Illuminate\Console\Command;

class SystemFirstLiveCheckCommand extends Command
{
    protected $signature = 'system:first-live-check';

    protected $description = 'Run safe first-live production environment validation checks.';

    public function handle(
        ProductionEnvironmentValidationService $environment,
        ServerReadinessService $server,
        FirstLiveSmokeTestService $smoke,
    ): int {
        $sections = [
            'environment' => $environment->report(),
            'server' => $server->report(),
            'smoke' => $smoke->report(),
        ];

        $blockers = collect($sections)->sum(fn (array $section): int => count($section['blockers']));
        $warnings = collect($sections)->sum(fn (array $section): int => count($section['warnings']));
        $passed = collect($sections)->sum(fn (array $section): int => count($section['passed']));
        $state = $blockers > 0 ? 'blocked' : ($warnings > 0 ? 'warning' : 'ready');

        $this->info('First-live status: '.strtoupper($state));
        $this->line('Passed: '.$passed);
        $this->line('Warnings: '.$warnings);
        $this->line('Blockers: '.$blockers);

        foreach ($sections as $sectionName => $section) {
            foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
                foreach ($section[$key] as $item) {
                    $this->line("{$label}: {$sectionName}.{$item['name']} - {$item['message']}");
                }
            }
        }

        return $state === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
