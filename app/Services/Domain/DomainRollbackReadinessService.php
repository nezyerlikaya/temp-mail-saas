<?php

namespace App\Services\Domain;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class DomainRollbackReadinessService extends Service
{
    public function __construct(
        private readonly DomainPoolService $pool,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(?Domain $domain = null): array
    {
        $fallback = (string) config('domains.live_activation.rollback.fallback_domain', config('domains.public_mailbox.default_domain', 'example.test'));
        $fallbackDomain = Domain::query()
            ->where('domain', $fallback)
            ->where('status', DomainStatus::Active)
            ->where('onboarding_state', DomainOnboardingState::Active)
            ->first();
        $allowed = $this->pool->allowedDomainNames();
        $checks = [
            $this->check('domain_rollback_documented', (bool) config('domains.live_activation.rollback.rollback_documented', true), 'Domain rollback process is documented.', 'Document domain rollback process.', 'blocker'),
            $this->check('fallback_domain_ready', ! (bool) config('domains.live_activation.rollback.require_fallback_ready', true) || $fallbackDomain instanceof Domain, 'Fallback domain is ready.', 'Fallback domain is not active and onboarded.', 'blocker'),
            $this->check('suspension_readiness', (bool) config('domains.live_activation.rollback.suspension_ready', true), 'Domain suspension path is ready.', 'Review domain suspension path.', 'blocker'),
            $this->check('mailbox_generation_safety', (bool) config('domains.live_activation.rollback.mailbox_generation_safe', true) && $allowed !== [], 'Mailbox generation remains safe during rollback.', 'Mailbox generation safety needs review.', 'blocker'),
            $this->check('fallback_not_current_domain', ! $domain instanceof Domain || $domain->domain !== $fallback || count($allowed) > 1, 'Fallback selection remains available.', 'Fallback domain should be separate from the reviewed domain.', 'warning'),
        ];
        $report = $this->summarize($checks);

        $this->operations->log(
            OperationCategory::Domain,
            'live_domain_rollback_reviewed',
            $report['blockers'] === [] ? OperationSeverity::Info : OperationSeverity::Warning,
            OperationStatus::Detected,
            'live-domain-readiness',
            'Live domain rollback readiness reviewed.',
            [
                'domain_id' => $domain?->id,
                'fallback_ready' => $fallbackDomain instanceof Domain,
                'blocker_count' => count($report['blockers']),
                'warning_count' => count($report['warnings']),
            ],
        );

        return $report;
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
}
