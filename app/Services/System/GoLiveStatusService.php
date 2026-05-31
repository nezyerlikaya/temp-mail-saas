<?php

namespace App\Services\System;

use App\Services\Service;

final class GoLiveStatusService extends Service
{
    public function __construct(private readonly LaunchChecklistService $checklist)
    {
    }

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
            'target' => $report['target'],
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
            'blocked' => 'Go-live is blocked by '.count($report['blockers']).' blocker(s).',
            'warning' => 'Go-live can proceed after reviewing '.count($report['warnings']).' warning(s).',
            default => 'Go-live readiness checks passed.',
        };
    }
}
