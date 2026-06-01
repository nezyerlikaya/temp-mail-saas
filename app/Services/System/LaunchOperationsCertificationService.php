<?php

namespace App\Services\System;

use App\Services\Operations\First24HourMonitoringService;
use App\Services\Operations\LaunchDayIncidentService;
use App\Services\Operations\RollbackTriggerReviewService;
use App\Services\Service;

final class LaunchOperationsCertificationService extends Service
{
    public function __construct(
        private readonly First24HourMonitoringService $monitoring,
        private readonly RollbackTriggerReviewService $rollback,
        private readonly LaunchDayIncidentService $incidents,
        private readonly SupportReadinessService $support,
    ) {}

    public function certify(): array
    {
        $monitoring = $this->monitoring->report();
        $rollback = $this->rollback->review();
        $incidents = $this->incidents->review();
        $support = $this->support->report();
        $checks = [
            $this->check('monitoring', $monitoring['status'] !== 'critical', 'Launch monitoring is ready.', 'Launch monitoring is critical.', 'blocked'),
            $this->check('rollback', $rollback['status'] !== 'rollback-recommended', 'Rollback review is acceptable.', 'Rollback is recommended.', 'blocked'),
            $this->check('incident', $incidents['status'] !== 'critical', 'Incident readiness is acceptable.', 'Critical incidents need review.', 'blocked'),
            $this->check('support', $support['status'] !== 'blocked', 'Support readiness is acceptable.', 'Support readiness is blocked.', 'blocked'),
            $this->check('controlled_rollout', (bool) config('production.public_launch.rollout.controlled_rollout_documented', true), 'Controlled rollout is documented.', 'Document controlled rollout.', 'warning'),
            $this->check('traffic_expansion', (bool) config('production.public_launch.rollout.traffic_expansion_manual', true), 'Traffic expansion remains manual.', 'Traffic expansion control needs review.', 'warning'),
            $this->check('rollback_owner', (bool) config('production.public_launch.rollout.rollback_owner_assigned', true), 'Rollback owner is assigned.', 'Assign rollback owner.', 'warning'),
            $this->check('support_coverage', (bool) config('production.public_launch.rollout.support_coverage_confirmed', true), 'Support coverage is confirmed.', 'Confirm support coverage.', 'warning'),
        ];
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = collect($checks)->where('classification', 'warning')->values()->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified'),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'checks' => $checks,
            'monitoring' => $monitoring,
            'rollback' => $rollback,
            'incidents' => $incidents,
            'support' => $support,
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }
}
