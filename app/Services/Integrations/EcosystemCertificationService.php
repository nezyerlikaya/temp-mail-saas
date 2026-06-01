<?php

namespace App\Services\Integrations;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class EcosystemCertificationService extends Service
{
    public function __construct(
        private readonly IntegrationEcosystemService $ecosystem,
        private readonly ConnectorHealthService $connectors,
        private readonly WebhookEcosystemService $webhooks,
        private readonly PlatformDependencyService $dependencies,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->operations->log(
            OperationCategory::System,
            'ecosystem_review_started',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'ecosystem-intelligence',
            'Ecosystem review started.',
        );

        $ecosystem = $this->ecosystem->review();
        $connectors = $this->connectors->review();
        $webhooks = $this->webhooks->review();
        $dependencies = $this->dependencies->review();
        $checks = [
            $this->check('integration_readiness', $ecosystem['blockers'] === [], 'Integration readiness is certified.', 'Integration readiness is blocked.', 'blocked'),
            $this->check('connector_readiness', $connectors['blockers'] === [], 'Connector readiness is certified.', 'Connector readiness is blocked.', 'blocked'),
            $this->check('webhook_readiness', $webhooks['blockers'] === [], 'Webhook readiness is certified.', 'Webhook readiness is blocked.', 'blocked'),
            $this->check('dependency_readiness', $dependencies['blockers'] === [], 'Dependency readiness is certified.', 'Dependency readiness is blocked.', 'blocked'),
        ];
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = [
            ...$ecosystem['warnings'],
            ...$connectors['warnings'],
            ...$webhooks['warnings'],
            ...$dependencies['warnings'],
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified');

        if ($status === 'certified') {
            $this->operations->log(
                OperationCategory::System,
                'ecosystem_certified',
                OperationSeverity::Info,
                OperationStatus::Detected,
                'ecosystem-intelligence',
                'Ecosystem readiness certified.',
            );
        }

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect($warnings)->pluck('message')->unique()->values()->all(),
            'ecosystem' => $ecosystem,
            'connectors' => $connectors,
            'webhooks' => $webhooks,
            'dependencies' => $dependencies,
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }
}
