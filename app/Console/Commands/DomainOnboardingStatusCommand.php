<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\Domain\DomainOnboardingService;
use Illuminate\Console\Command;

class DomainOnboardingStatusCommand extends Command
{
    protected $signature = 'domain:onboarding-status';

    protected $description = 'Display a safe domain onboarding readiness summary.';

    public function handle(DomainOnboardingService $onboarding): int
    {
        $domains = Domain::query()->get();
        $reports = $domains->map(fn (Domain $domain): array => $onboarding->readiness($domain));
        $blockers = $reports->sum(fn (array $report): int => count($report['blockers']));
        $warnings = $reports->sum(fn (array $report): int => count($report['warnings']));
        $recommendations = $reports->sum(fn (array $report): int => count($report['recommendations']));
        $ready = $reports->filter(fn (array $report): bool => $report['blockers'] === [])->count();
        $state = $blockers > 0 ? 'blocked' : ($warnings > 0 ? 'warning' : 'ready');

        $this->info('Domain onboarding status: '.strtoupper($state));
        $this->line('Domains: '.$domains->count());
        $this->line('Ready: '.$ready);
        $this->line('Blockers: '.$blockers);
        $this->line('Warnings: '.$warnings);
        $this->line('Recommendations: '.$recommendations);

        foreach ((array) config('domains.onboarding.states', []) as $onboardingState) {
            $count = $domains->filter(
                fn (Domain $domain): bool => $domain->onboarding_state->value === $onboardingState,
            )->count();

            $this->line("State {$onboardingState}: {$count}");
        }

        return $blockers > 0 ? self::FAILURE : self::SUCCESS;
    }
}
