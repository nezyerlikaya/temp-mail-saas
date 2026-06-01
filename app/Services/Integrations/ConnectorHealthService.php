<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\ConnectorContract;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\UserIntegrationStatus;
use App\Models\UserIntegration;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class ConnectorHealthService extends Service
{
    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function review(): array
    {
        $connectors = collect(config('integrations.connectors', []));
        $missing = $connectors->filter(fn (string $class): bool => ! class_exists($class))->keys()->values()->all();
        $invalid = $connectors
            ->filter(fn (string $class): bool => class_exists($class) && ! is_subclass_of($class, ConnectorContract::class))
            ->keys()
            ->values()
            ->all();
        $connected = UserIntegration::query()->where('status', UserIntegrationStatus::Connected)->count();
        $inactive = UserIntegration::query()->whereIn('status', [UserIntegrationStatus::Disconnected, UserIntegrationStatus::Suspended])->count();
        $checks = [
            $this->check('connector_status', $connectors->isNotEmpty(), 'Connector registry contains connectors.', 'Connector registry is empty.', 'blocked'),
            $this->check('connector_configuration', (bool) config('integrations.ecosystem.connectors.allow_missing_classes', false) || $missing === [], 'Connector classes are available.', 'Connector classes are missing.', 'blocked'),
            $this->check('connector_contracts', ! (bool) config('integrations.ecosystem.connectors.require_contracts', true) || $invalid === [], 'Connector contracts are valid.', 'Connector contracts need review.', 'blocked'),
            $this->check('connector_lifecycle', $inactive < (int) config('integrations.ecosystem.connectors.inactive_connection_warning_count', 1), 'Connector lifecycle is healthy.', 'Inactive connector connections need review.', 'warning'),
        ];
        $summary = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::System,
            'connector_review_completed',
            $summary['status'] === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'ecosystem-intelligence',
            'Connector health review recorded.',
            [
                'status' => $summary['status'],
                'connector_count' => $connectors->count(),
                'connected_count' => $connected,
                'inactive_count' => $inactive,
                'missing_count' => count($missing),
                'invalid_count' => count($invalid),
            ],
        );

        return [
            ...$summary,
            'connector_count' => $connectors->count(),
            'connected_count' => $connected,
            'inactive_count' => $inactive,
            'missing_count' => count($missing),
            'invalid_count' => count($invalid),
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
