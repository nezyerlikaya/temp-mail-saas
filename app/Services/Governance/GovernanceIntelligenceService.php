<?php

namespace App\Services\Governance;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class GovernanceIntelligenceService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $checks = [
            $this->check('platform_readiness', (bool) config('governance.governance.platform_ready', true), 'Platform readiness governance is available.', 'Platform readiness governance needs review.', 'blocked'),
            $this->check('policy_readiness', (bool) config('governance.governance.policy_ready', true), 'Policy readiness is available.', 'Policy readiness needs review.', 'warning'),
            $this->check('operational_controls', (bool) config('governance.governance.operational_controls_ready', true), 'Operational controls are available.', 'Operational controls need review.', 'blocked'),
            $this->check('governance_maturity', (bool) config('governance.governance.maturity_ready', true), 'Governance maturity review is available.', 'Governance maturity needs review.', 'warning'),
        ];
        $summary = $this->summarize($checks);
        $state = $summary['status'] === 'blocked' ? 'risk' : ($summary['status'] === 'warning' ? 'attention' : 'healthy');

        $this->operations->log(
            OperationCategory::System,
            'governance_review_completed',
            $state === 'risk' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'governance',
            'Governance intelligence review recorded.',
            [
                'state' => $state,
                'warning_count' => count($summary['warnings']),
                'blocker_count' => count($summary['blockers']),
            ],
        );

        return [
            ...$summary,
            'state' => $state,
        ];
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
