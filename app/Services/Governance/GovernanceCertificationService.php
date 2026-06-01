<?php

namespace App\Services\Governance;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class GovernanceCertificationService extends Service
{
    public function __construct(
        private readonly GovernanceIntelligenceService $governance,
        private readonly StrategicOperationsService $operations,
        private readonly PlatformRiskService $risk,
        private readonly OperationalMaturityService $maturity,
        private readonly OperationsLoggerService $events,
    ) {}

    public function report(): array
    {
        $this->events->log(
            OperationCategory::System,
            'governance_review_started',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'governance',
            'Governance review started.',
        );

        $governance = $this->governance->review();
        $operations = $this->operations->review();
        $risk = $this->risk->review();
        $maturity = $this->maturity->review();
        $checks = [
            $this->check('governance_readiness', ! (bool) config('governance.certification.governance', true) || $governance['blockers'] === [], 'Governance readiness is certified.', 'Governance readiness is blocked.', 'blocked'),
            $this->check('operational_maturity', ! (bool) config('governance.certification.maturity', true) || $maturity['blockers'] === [], 'Operational maturity is certified.', 'Operational maturity is blocked.', 'blocked'),
            $this->check('risk_readiness', ! (bool) config('governance.certification.risk', true) || $risk['critical'] === [], 'Platform risk readiness is certified.', 'Platform risk readiness is blocked.', 'blocked'),
            $this->check('sustainability_readiness', ! (bool) config('governance.certification.sustainability', true) || $operations['blockers'] === [], 'Sustainability readiness is certified.', 'Sustainability readiness is blocked.', 'blocked'),
        ];
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = [
            ...$governance['warnings'],
            ...$operations['warnings'],
            ...$maturity['warnings'],
            ...$risk['high'],
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified');

        if ($status === 'certified') {
            $this->events->log(
                OperationCategory::System,
                'governance_certified',
                OperationSeverity::Info,
                OperationStatus::Detected,
                'governance',
                'Governance readiness certified.',
            );
        }

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([
                ...collect($warnings)->pluck('message')->all(),
                ...$risk['recommendations'],
            ])->filter()->unique()->values()->all(),
            'governance' => $governance,
            'strategic_operations' => $operations,
            'risk' => $risk,
            'maturity' => $maturity,
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }
}
