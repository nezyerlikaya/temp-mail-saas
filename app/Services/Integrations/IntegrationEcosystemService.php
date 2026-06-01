<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Integration;
use App\Models\UserIntegration;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class IntegrationEcosystemService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $registered = Schema::hasTable('integrations') ? Integration::query()->count() : 0;
        $active = Schema::hasTable('integrations') ? Integration::query()->where('status', IntegrationStatus::Active)->count() : 0;
        $connections = Schema::hasTable('user_integrations') ? UserIntegration::query()->count() : 0;
        $connectorCount = count(config('integrations.connectors', []));
        $checks = [
            $this->check('registered_integrations', ! (bool) config('integrations.ecosystem.readiness.require_registry', true) || Schema::hasTable('integrations'), 'Integration registry is available.', 'Integration registry is missing.', 'blocked'),
            $this->check('connector_readiness', ! (bool) config('integrations.ecosystem.readiness.require_connectors', true) || $connectorCount > 0, 'Connector registry is available.', 'Connector registry needs review.', 'blocked'),
            $this->check('configuration_readiness', ! (bool) config('integrations.ecosystem.readiness.require_configuration', true) || is_array(config('integrations.registry.compatibility')), 'Integration configuration readiness is available.', 'Integration configuration needs review.', 'warning'),
            $this->check('ecosystem_coverage', $registered >= (int) config('integrations.ecosystem.readiness.coverage_warning_minimum', 1), 'Ecosystem coverage has registered integrations.', 'Ecosystem coverage needs more registered integrations.', 'warning'),
        ];
        $summary = $this->summarize($checks);
        $state = $summary['status'] === 'blocked' ? 'risk' : ($summary['status'] === 'warning' ? 'attention' : 'healthy');

        $this->operations->log(
            OperationCategory::System,
            'ecosystem_review_completed',
            $state === 'risk' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'ecosystem-intelligence',
            'Integration ecosystem review recorded.',
            [
                'state' => $state,
                'registered_count' => $registered,
                'active_count' => $active,
                'connection_count' => $connections,
                'connector_count' => $connectorCount,
            ],
        );

        return [
            ...$summary,
            'state' => $state,
            'registered_integrations' => $registered,
            'active_integrations' => $active,
            'connection_count' => $connections,
            'connector_count' => $connectorCount,
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
