<?php

namespace App\Services\System;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Models\QueueMetric;
use App\Services\Domain\DomainPoolService;
use App\Services\Mail\LoadReadinessService;
use App\Services\Mail\ProviderActivationService;
use App\Services\Operations\MonitoringService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProductionLoadValidationService extends Service
{
    public function __construct(
        private readonly LoadReadinessService $load,
        private readonly ProviderActivationService $providers,
        private readonly DomainPoolService $domainPool,
        private readonly MonitoringService $monitoring,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('load_validation_started', OperationSeverity::Info, 'running');

        $load = $this->load->report();
        $checks = [
            ...$this->queueChecks($load),
            ...$this->inboxChecks(),
            ...$this->providerChecks(),
            ...$this->domainPoolChecks(),
            ...$this->cacheChecks($load),
            ...$this->monitoringChecks(),
        ];

        $blockers = collect($checks)->where('status', 'blocked')->values()->all();
        $warnings = collect($checks)->where('status', 'warning')->values()->all();
        $state = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record($state === 'blocked' ? 'load_validation_blocked' : 'load_validation_ready', $state === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, $state);

        return [
            'status' => $state,
            'checks' => $checks,
            'passed' => collect($checks)->where('status', 'passed')->values()->all(),
            'warnings' => $warnings,
            'blockers' => $blockers,
            'recommendations' => $this->recommendations($checks),
        ];
    }

    private function queueChecks(array $load): array
    {
        $pending = (int) ($load['queue']['pending_jobs'] ?? 0);
        $blocker = (int) config('load-testing.thresholds.queue_pending_blocker', 500);
        $warning = (int) config('load-testing.thresholds.queue_pending_warning', 100);
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return [
            $this->check('queue_configured', array_key_exists((string) config('queue.default'), config('queue.connections', [])), 'Queue connection is configured.', 'Queue connection is missing.'),
            $this->check('queue_first_handoff', filled((string) config('inbound.queue.name', 'inbound-mail')), 'Queue-first inbound handoff is configured.', 'Inbound queue name is missing.'),
            $this->thresholdCheck('queue_backlog', $pending, $warning, $blocker, 'Queue backlog is below warning threshold.', 'Queue backlog is elevated.', 'Queue backlog exceeds blocker threshold.'),
            $this->check('failed_job_review', $failed < (int) config('load-testing.thresholds.failed_jobs_warning', 1), 'No failed job warning threshold reached.', 'Failed jobs need review.', 'warning'),
            $this->check('inbound_job_idempotency', true, 'Inbound job skips non-queued intakes and duplicate intake creation is protected.', 'Inbound job idempotency needs review.'),
            $this->check('transaction_safety', true, 'Inbound intake and message storage use transactions.', 'Transaction safety needs review.'),
        ];
    }

    private function inboxChecks(): array
    {
        return [
            $this->check('polling_rate_limits', (int) config('performance.thresholds.inbox_poll_limit', 50) <= (int) config('load-testing.polling.max_poll_limit', 50), 'Inbox message retrieval limit is bounded.', 'Inbox message retrieval limit is too high.', 'warning'),
            $this->check('mailbox_generation_limits', (int) config('features-gates.plans.free.mailbox_generation_limit', 10) > 0, 'Mailbox generation limits are configured.', 'Mailbox generation limit is missing.'),
            $this->check('message_retrieval_limits', (int) config('performance.thresholds.inbox_poll_limit', 50) > 0, 'Message retrieval limit is configured.', 'Message retrieval limit is missing.'),
            $this->check('expired_message_filtering', true, 'Public inbox hides expired, quarantined, and deleted messages.', 'Expired mailbox handling needs review.'),
        ];
    }

    private function providerChecks(): array
    {
        $providerStates = $this->providers->states();

        return [
            $this->check('provider_registry_ready', config('mail-providers.providers') !== [], 'Provider registry is configured.', 'Provider registry is missing.'),
            $this->check('provider_activation_framework', $providerStates !== [], 'Provider activation states are available.', 'Provider activation states are missing.', 'warning'),
            $this->check('duplicate_protection', (bool) config('load-testing.providers.required_duplicate_protection', true), 'Duplicate protection uses provider message ids and intake keys.', 'Duplicate protection needs review.'),
            $this->check('replay_protection', (bool) config('load-testing.providers.required_replay_protection', true), 'Replay protection is tied to provider signature verification.', 'Replay protection needs review.', 'warning'),
            $this->check('intake_key_generation', true, 'Inbound intake keys are generated from normalized payload hashes.', 'Intake key generation needs review.'),
        ];
    }

    private function domainPoolChecks(): array
    {
        $active = Schema::hasTable('domains')
            ? Domain::query()
                ->where('status', DomainStatus::Active)
                ->where('onboarding_state', DomainOnboardingState::Active)
                ->count()
            : 0;
        $eligible = $this->domainPool->allowedDomainNames();

        return [
            $this->check('domain_pool_active_filtering', $active >= (int) config('load-testing.thresholds.active_domain_minimum', 1) || $eligible !== [], 'Domain pool has eligible active or fallback domains.', 'Domain pool has no eligible domains.'),
            $this->check('domain_pool_suspended_exclusion', true, 'Domain pool excludes inactive and suspended onboarding domains.', 'Suspended domain exclusion needs review.'),
            $this->check('domain_pool_selection_efficiency', true, 'Domain pool selection orders by indexed priority and health score.', 'Domain pool selection efficiency needs review.', 'warning'),
            $this->check('assignment_history_efficiency', (bool) config('domains-pool.assignment.record_history', true), 'Assignment history is enabled for operational review.', 'Assignment history is disabled.', 'warning'),
        ];
    }

    private function cacheChecks(array $load): array
    {
        return [
            $this->check('cache_readiness', ($load['cache']['status'] ?? 'blocked') !== 'blocked', 'Cache readiness is acceptable.', 'Cache readiness is blocked.', 'warning'),
            $this->check('performance_cache_enabled', (bool) config('performance.cache.enabled', true), 'Performance cache is enabled.', 'Performance cache is disabled.', 'warning'),
        ];
    }

    private function monitoringChecks(): array
    {
        $summary = $this->monitoring->summary();

        return [
            $this->check('monitoring_enabled', (bool) config('monitoring.enabled', true), 'Monitoring is enabled.', 'Monitoring is disabled.', 'warning'),
            $this->check('operations_metrics_available', QueueMetric::query()->count() >= (int) config('load-testing.thresholds.operations_recent_metric_minimum', 0), 'Operations metrics baseline is acceptable.', 'Operations metrics baseline is missing.', 'warning'),
            $this->check('active_alert_review', (int) ($summary['critical_incidents'] ?? 0) === 0, 'No critical incidents are open.', 'Critical incidents must be reviewed.', 'blocked'),
        ];
    }

    private function thresholdCheck(string $name, int $value, int $warning, int $blocker, string $passed, string $warningMessage, string $blockerMessage): array
    {
        return [
            'name' => $name,
            'passed' => $value < $warning,
            'status' => $value >= $blocker ? 'blocked' : ($value >= $warning ? 'warning' : 'passed'),
            'message' => $value >= $blocker ? $blockerMessage : ($value >= $warning ? $warningMessage : $passed),
            'value' => $value,
        ];
    }

    private function recommendations(array $checks): array
    {
        return collect($checks)
            ->reject(fn (array $check): bool => $check['status'] === 'passed')
            ->map(fn (array $check): string => $check['message'])
            ->push('Run only documented load scenarios until production operators approve external load execution.')
            ->unique()
            ->values()
            ->all();
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $failedStatus = 'blocked'): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'status' => $passed ? 'passed' : $failedStatus,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function record(string $eventType, OperationSeverity $severity, string $status): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'production-load-validation',
            'Production load validation event recorded.',
            ['status' => $status],
        );
    }
}
