<?php

namespace App\Services\Mail;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class ProviderRollbackReadinessService extends Service
{
    public function __construct(
        private readonly ProviderRegistryService $registry,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(?string $provider = null): array
    {
        $provider = $this->normalize($provider ?? (string) config('mail-providers.default', 'mailgun'));
        $fallback = $this->normalize((string) config('mail-providers.live_activation.rollback.fallback_provider', 'local'));
        $fallbackHealth = $this->registry->health($fallback);
        $checks = [
            $this->check('provider_rollback_documented', (bool) config('mail-providers.live_activation.rollback.rollback_documented', true), 'Provider rollback process is documented.', 'Document provider rollback process.', 'blocker'),
            $this->check('fallback_provider_ready', ! (bool) config('mail-providers.live_activation.rollback.require_fallback_ready', true) || ($fallbackHealth['configured'] && $fallbackHealth['enabled']), 'Fallback provider is ready.', 'Fallback provider is not ready.', 'blocker'),
            $this->check('suspension_readiness', (bool) config('mail-providers.live_activation.rollback.suspension_ready', true), 'Provider suspension path is ready.', 'Review provider suspension path.', 'blocker'),
            $this->check('queue_safety_review', filled((string) config('inbound.queue.name', 'inbound-mail')) && (string) config('queue.default', 'sync') !== 'sync', 'Queue safety review is ready.', 'Queue safety review requires a worker-backed queue.', 'blocker'),
            $this->check('queue_drain_documented', (bool) config('mail-providers.live_activation.rollback.queue_drain_documented', true), 'Queue drain guidance is documented.', 'Document queue drain guidance.', 'warning'),
        ];
        $report = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::Mail,
            'live_provider_rollback_reviewed',
            $report['blockers'] === [] ? OperationSeverity::Info : OperationSeverity::Warning,
            OperationStatus::Detected,
            'live-provider-readiness',
            'Live provider rollback readiness reviewed.',
            [
                'provider' => $provider,
                'fallback_provider' => $fallback,
                'blocker_count' => count($report['blockers']),
                'warning_count' => count($report['warnings']),
            ],
        );

        return [
            'provider' => $provider,
            'fallback_provider' => $fallback,
            ...$report,
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

    private function summarize(array $checks): array
    {
        return [
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }

    private function normalize(string $provider): string
    {
        return $provider === 'amazon_ses' ? 'ses' : $provider;
    }
}
