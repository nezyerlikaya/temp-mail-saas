<?php

namespace App\Services\Enterprise;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class EnterpriseCertificationService extends Service
{
    public function __construct(
        private readonly EnterpriseAccountHealthService $health,
        private readonly OrganizationLifecycleService $lifecycle,
        private readonly AccountGovernanceService $governance,
        private readonly MembershipIntelligenceService $membership,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->operations->log(
            OperationCategory::System,
            'enterprise_review_started',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'enterprise-readiness',
            'Enterprise readiness review started.',
        );

        $health = $this->health->review();
        $lifecycle = $this->lifecycle->review();
        $governance = $this->governance->review();
        $membership = $this->membership->report();
        $checks = [
            $this->check('account_health_readiness', $health['state'] !== 'risk', 'Enterprise account health is ready.', 'Enterprise account health needs review.', 'blocked'),
            $this->check('governance_readiness', $governance['blockers'] === [], 'Enterprise governance is ready.', 'Enterprise governance is blocked.', 'blocked'),
            $this->check('lifecycle_readiness', $lifecycle['blockers'] === [], 'Organization lifecycle readiness is available.', 'Organization lifecycle readiness is blocked.', 'blocked'),
            $this->check('membership_readiness', $membership['status'] !== 'blocked', 'Membership intelligence is ready.', 'Membership intelligence is blocked.', 'blocked'),
        ];
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = [
            ...$governance['warnings'],
            ...$lifecycle['warnings'],
            ...($health['state'] === 'attention' ? [['name' => 'account_health_attention', 'message' => 'Enterprise account health needs attention.']] : []),
            ...($membership['status'] === 'warning' ? [['name' => 'membership_attention', 'message' => 'Inactive membership trends need review.']] : []),
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified');

        if ($status === 'certified') {
            $this->operations->log(
                OperationCategory::System,
                'enterprise_certified',
                OperationSeverity::Info,
                OperationStatus::Detected,
                'enterprise-readiness',
                'Enterprise readiness certified.',
            );
        }

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect($warnings)->pluck('message')->unique()->values()->all(),
            'account_health' => $health,
            'governance' => $governance,
            'lifecycle' => $lifecycle,
            'membership' => $membership,
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }
}
