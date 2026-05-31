<?php

namespace App\Services\System;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Services\Domain\DomainOnboardingService;
use App\Services\Mail\ProviderActivationService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class ProductionDeploymentReadinessService extends Service
{
    public function __construct(
        private readonly ServerProfileValidationService $server,
        private readonly ProductionEnvironmentValidationService $environment,
        private readonly ProviderActivationService $providers,
        private readonly DomainOnboardingService $domains,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('deployment_readiness_started');

        $sections = [
            'server' => $this->server->report(),
            'environment' => $this->environment->report(),
            'queue' => $this->queueReview(),
            'scheduler' => $this->schedulerReview(),
            'provider' => $this->providerReview(),
            'domain' => $this->domainReview(),
        ];
        $blockers = $this->issues($sections, 'blockers');
        $warnings = $this->issues($sections, 'warnings');
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record('deployment_readiness_'.$status, $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
        ]);

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $this->recommendations($sections),
            'sections' => $sections,
        ];
    }

    private function queueReview(): array
    {
        return $this->summarize([
            $this->check('queue_workers_documented', (bool) config('production.deployment_readiness.queue.workers_documented', true), 'Queue worker requirements are documented.', 'Document queue worker requirements.', 'blocker'),
            $this->check('queue_restart_strategy', (bool) config('production.deployment_readiness.queue.restart_strategy_documented', true), 'Queue restart strategy is documented.', 'Document the queue restart strategy.', 'warning'),
            $this->check('failed_jobs_strategy', (bool) config('production.deployment_readiness.queue.failed_jobs_strategy_documented', true), 'Failed job strategy is documented.', 'Document failed job review and retry strategy.', 'warning'),
            $this->check('supervisor_compatibility', (bool) config('production.deployment_readiness.queue.supervisor_compatibility_documented', true), 'Supervisor compatibility notes are documented.', 'Document supervisor compatibility notes.', 'warning'),
        ]);
    }

    private function schedulerReview(): array
    {
        return $this->summarize([
            $this->check('cron_requirements', (bool) config('production.deployment_readiness.scheduler.cron_requirements_documented', true), 'Cron requirements are documented.', 'Document cron requirements.', 'blocker'),
            $this->check('scheduler_readiness', (bool) config('production.deployment_readiness.scheduler.scheduler_ready', true), 'Scheduler readiness is confirmed.', 'Review scheduler readiness.', 'blocker'),
            $this->check('cleanup_schedule', (bool) config('production.deployment_readiness.scheduler.cleanup_schedule_documented', true), 'Cleanup scheduling is documented.', 'Document cleanup scheduling.', 'warning'),
            $this->check('monitoring_schedule', (bool) config('production.deployment_readiness.scheduler.monitoring_schedule_documented', true), 'Monitoring scheduling is documented.', 'Document monitoring scheduling.', 'warning'),
        ]);
    }

    private function providerReview(): array
    {
        $provider = (string) config('production.deployment_readiness.provider.name', 'mailgun');
        $report = $this->providers->readiness($provider);
        $state = $report['states'][$provider] ?? 'inactive';

        return $this->summarize([
            $this->check('provider_activation', $state === 'active', 'Provider activation readiness is confirmed.', 'Configured provider is not active.', 'blocker'),
            $this->check('provider_webhook_readiness', $report['blockers'] === [], 'Provider webhook readiness is confirmed.', 'Provider readiness has blockers.', 'blocker'),
            $this->check('provider_signing_secret_readiness', filled((string) config("mail-providers.providers.{$provider}.signing_key")), 'Provider signing secret is configured outside the report.', 'Provider signing secret readiness needs review.', 'blocker'),
            $this->check('provider_rollback', (bool) config('production.deployment_readiness.provider.rollback_documented', true), 'Provider rollback strategy is documented.', 'Document provider rollback strategy.', 'warning'),
        ]);
    }

    private function domainReview(): array
    {
        $domain = Domain::query()
            ->where('status', DomainStatus::Active)
            ->where('onboarding_state', DomainOnboardingState::Active)
            ->first();
        $ready = $domain !== null && $this->domains->readiness($domain)['blockers'] === [];

        return $this->summarize([
            $this->check('domain_onboarding', $ready, 'An active onboarded domain is ready.', 'No active onboarded domain is ready.', 'blocker'),
            $this->check('mx_checklist', (bool) config('production.deployment_readiness.domain.mx_checklist', true), 'MX readiness checklist is documented.', 'Document the MX readiness checklist.', 'warning'),
            $this->check('spf_checklist', (bool) config('production.deployment_readiness.domain.spf_checklist', true), 'SPF readiness checklist is documented.', 'Document the SPF readiness checklist.', 'warning'),
            $this->check('dkim_checklist', (bool) config('production.deployment_readiness.domain.dkim_checklist', true), 'DKIM readiness checklist is documented.', 'Document the DKIM readiness checklist.', 'warning'),
            $this->check('dmarc_checklist', (bool) config('production.deployment_readiness.domain.dmarc_checklist', true), 'DMARC readiness checklist is documented.', 'Document the DMARC readiness checklist.', 'warning'),
        ]);
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

    private function issues(array $sections, string $type): array
    {
        return collect($sections)
            ->flatMap(fn (array $section, string $category): array => collect($section[$type])
                ->map(fn (array $issue): array => ['category' => $category, ...$issue])
                ->all())
            ->values()
            ->all();
    }

    private function recommendations(array $sections): array
    {
        return collect($this->issues($sections, 'blockers'))
            ->merge($this->issues($sections, 'warnings'))
            ->map(fn (array $issue): string => $issue['message'])
            ->unique()
            ->values()
            ->all();
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'production-deployment-readiness',
            'Production deployment readiness event recorded.',
            $metadata,
        );
    }
}
