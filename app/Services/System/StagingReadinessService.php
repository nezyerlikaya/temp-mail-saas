<?php

namespace App\Services\System;

use App\Services\Domain\DomainPoolService;
use App\Services\Mail\LoadReadinessService;
use App\Services\Mail\ProviderConnectivityValidationService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class StagingReadinessService extends Service
{
    public function __construct(
        private readonly ProviderConnectivityValidationService $providers,
        private readonly DomainPoolService $domains,
        private readonly LoadReadinessService $load,
        private readonly InstallationService $installation,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function evaluate(): array
    {
        $this->record('staging_validation_started');

        $provider = $this->providers->report();
        $checks = [
            ...$provider['checks'],
            ...$this->domainChecks(),
            ...$this->queueChecks(),
            ...$this->installerChecks(),
        ];
        $blockers = collect($checks)->where('classification', 'blocker')->values()->all();
        $warnings = collect($checks)->where('classification', 'warning')->values()->all();
        $recommendations = collect($checks)->where('classification', 'recommendation')->values()->all();
        $state = match (true) {
            $blockers !== [] => 'blocked',
            $warnings !== [] => 'warning',
            default => 'ready',
        };

        $this->record($state === 'blocked' ? 'staging_validation_failed' : 'staging_validation_passed');

        return [
            'state' => $state,
            'summary' => $this->summary($state, $blockers, $warnings),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'checks' => $checks,
        ];
    }

    private function domainChecks(): array
    {
        $allowed = config('mail-providers.staging.allowed_domains', []);
        $allowed = is_array($allowed) ? array_values(array_filter($allowed)) : [];
        $available = $this->domains->allowedDomainNames();

        return [
            $this->check('domains', 'staging_allowed_domains_configured', $allowed !== [], 'Staging allowed domains are configured.', 'No staging allowed domains are configured.', 'warning', ['count' => count($allowed)]),
            $this->check('domains', 'domain_pool_fallback_available', $available !== [], 'Domain pool or fallback domains are available.', 'No eligible or fallback domains are available.', 'blocker', ['count' => count($available)]),
        ];
    }

    private function queueChecks(): array
    {
        $load = $this->load->report();

        return [
            $this->check('queue', 'inbound_queue_ready', ($load['queue']['status'] ?? 'warning') !== 'blocked', 'Inbound queue readiness is acceptable.', 'Inbound queue readiness is blocked.', 'blocker', ['status' => $load['queue']['status'] ?? 'unknown']),
            $this->check('queue', 'database_ready', ($load['database']['status'] ?? 'blocked') === 'ready', 'Database readiness is healthy.', 'Database readiness is not healthy.', 'blocker', ['status' => $load['database']['status'] ?? 'unknown']),
            $this->check('queue', 'cache_ready', in_array(($load['cache']['status'] ?? 'warning'), ['ready', 'warning'], true), 'Cache readiness is acceptable.', 'Cache readiness is blocked.', 'warning', ['status' => $load['cache']['status'] ?? 'unknown']),
        ];
    }

    private function installerChecks(): array
    {
        $status = $this->installation->status();

        return [
            $this->check('installer', 'installation_healthy', $status['healthy'] === true, 'Installation is complete and locked.', 'Installation is incomplete or installer lock is missing.', 'blocker', ['locked' => $status['lock']['locked']]),
            $this->check('installer', 'environment_recovery_clear', $status['recovery'] === false, 'Environment recovery is not required.', 'Environment recovery is required.', 'blocker', []),
        ];
    }

    private function check(string $category, string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification, array $metadata = []): array
    {
        return [
            'category' => $category,
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'informational' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
            'metadata' => $metadata,
        ];
    }

    private function summary(string $state, array $blockers, array $warnings): string
    {
        return match ($state) {
            'blocked' => 'Staging readiness is blocked by '.count($blockers).' blocker(s).',
            'warning' => 'Staging readiness has '.count($warnings).' warning(s) to review.',
            default => 'Staging readiness checks passed.',
        };
    }

    private function record(string $eventType): void
    {
        if (! (bool) config('mail-providers.staging.metrics_enabled', true)) {
            return;
        }

        $this->operations->log(
            \App\Enums\OperationCategory::Mail,
            $eventType,
            str_contains($eventType, 'failed') ? \App\Enums\OperationSeverity::Warning : \App\Enums\OperationSeverity::Info,
            \App\Enums\OperationStatus::Detected,
            'provider-staging',
            'Staging readiness validation event recorded.',
        );
    }
}
