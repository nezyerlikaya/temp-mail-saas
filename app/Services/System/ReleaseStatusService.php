<?php

namespace App\Services\System;

use App\Services\Service;

final class ReleaseStatusService extends Service
{
    public function __construct(
        private readonly ProductionReadinessChecklistService $checklist,
    ) {}

    public function evaluate(): array
    {
        $report = $this->checklist->report();
        $state = match (true) {
            count($report['blockers']) > 0 => 'blocked',
            count($report['warnings']) > 0 => 'warning',
            default => 'ready',
        };

        return [
            'state' => $state,
            'target' => (string) config('production.release.target', 'rc1'),
            'summary' => $this->summary($state, $report),
            'blockers' => $report['blockers'],
            'warnings' => $report['warnings'],
            'recommendations' => $report['recommendations'],
            'checks' => $report['checks'],
        ];
    }

    private function summary(string $state, array $report): string
    {
        return match ($state) {
            'blocked' => 'Release is blocked by '.count($report['blockers']).' blocker(s).',
            'warning' => 'Release can proceed with '.count($report['warnings']).' warning(s) to review.',
            default => 'Release readiness checks passed.',
        };
    }
}
