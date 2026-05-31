<?php

namespace App\Services\System;

use App\Services\Service;

final class FinalReleaseStatusService extends Service
{
    public function __construct(
        private readonly V1LaunchCertificationService $certification,
        private readonly RollbackReadinessService $rollback,
        private readonly PostLaunchMonitoringService $postLaunch,
    ) {}

    public function evaluate(): array
    {
        $certification = $this->certification->report();
        $rollback = $this->rollback->report();
        $postLaunch = $this->postLaunch->plan();
        $ready = $certification['status'] === 'certified' && $rollback['ready'] === true && $postLaunch['status'] === 'ready';

        return [
            'status' => $ready ? 'ready' : ($certification['status'] === 'blocked' ? 'blocked' : 'warning'),
            'confidence' => $ready ? 'high' : ($certification['blockers'] === [] ? 'medium' : 'low'),
            'launch_decision' => $ready ? 'Production Launch Ready' : 'Hold launch until readiness gaps are reviewed.',
            'certification' => $certification,
            'rollback' => [
                'ready' => $rollback['ready'],
                'risks' => $rollback['risks'],
            ],
            'post_launch' => $postLaunch,
        ];
    }
}
