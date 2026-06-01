<?php

namespace App\Services\Governance;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class StrategicOperationsService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $checks = [
            $this->check('operational_planning', (bool) config('governance.strategic_operations.planning_ready', true), 'Operational planning readiness is available.', 'Operational planning needs review.', 'warning'),
            $this->check('maintenance_review', (bool) config('governance.strategic_operations.maintenance_ready', true), 'Maintenance readiness is available.', 'Maintenance readiness needs review.', 'warning'),
            $this->check('incident_readiness', (bool) config('governance.strategic_operations.incident_readiness', true), 'Incident readiness is available.', 'Incident readiness needs review.', 'blocked'),
            $this->check('scalability_readiness', (bool) config('governance.strategic_operations.scalability_readiness', true), 'Scalability readiness is available.', 'Scalability readiness needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'strategic_operations_review_completed',
            $summary['status'] === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'governance',
            'Strategic operations review recorded.',
            [
                'status' => $summary['status'],
                'warning_count' => count($summary['warnings']),
                'blocker_count' => count($summary['blockers']),
            ],
        );

        return $summary;
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocked')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocked')->values()->all(),
            'checks' => $checks,
        ];
    }
}
