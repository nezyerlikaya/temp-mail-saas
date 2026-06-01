<?php

namespace App\Services\Integrations;

use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class PlatformDependencyService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('dependency_inventory', config('integrations.registry.compatibility.app') !== null, 'Integration dependency inventory is available.', 'Integration dependency inventory needs review.', 'warning'),
            $this->check('integration_dependency', ! (bool) config('integrations.ecosystem.dependencies.require_local_connector', true) || array_key_exists('local', config('integrations.connectors', [])), 'Local connector dependency is available.', 'Local connector dependency is missing.', 'blocked'),
            $this->check('operational_dependency', ! (bool) config('integrations.ecosystem.dependencies.require_operations_events', true) || Schema::hasTable('operations_events'), 'Operations event dependency is available.', 'Operations event dependency is missing.', 'blocked'),
            $this->check('webhook_dependency', ! (bool) config('integrations.ecosystem.dependencies.require_webhook_tables', true) || (Schema::hasTable('outbound_webhooks') && Schema::hasTable('webhook_deliveries')), 'Webhook dependency tables are available.', 'Webhook dependency tables need review.', 'blocked'),
        ];

        return $this->summarize($checks);
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
