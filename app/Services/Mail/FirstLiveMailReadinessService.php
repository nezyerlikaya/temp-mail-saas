<?php

namespace App\Services\Mail;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Services\Domain\LiveDomainReadinessService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class FirstLiveMailReadinessService extends Service
{
    public function __construct(
        private readonly LiveProviderReadinessService $providers,
        private readonly LiveDomainReadinessService $domains,
        private readonly ProviderRollbackReadinessService $providerRollback,
        private readonly MailReceptionTraceService $trace,
        private readonly PublicMailboxService $mailboxes,
        private readonly PublicInboxMessageService $inbox,
        private readonly FirstLiveMailDiagnosticsService $diagnostics,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(
        ?string $provider = null,
        ?string $domain = null,
        ?string $mailbox = null,
        ?string $intakeUuid = null,
        ?string $providerMessageId = null,
        ?string $messageUuid = null,
    ): array {
        $this->record('first_live_mail_review_started');

        $provider ??= (string) config('mail-providers.first_live_mail.provider', 'mailgun');
        $domain ??= config('mail-providers.first_live_mail.domain');
        $sections = [
            'provider' => $this->providers->report($provider),
            'domain' => $this->domains->report(is_string($domain) ? $domain : null),
            'webhook' => $this->webhookReview($provider, $domain),
            'queue' => $this->queueReview(),
            'mailbox' => $this->mailboxReview($domain, $mailbox),
            'inbox' => $this->inboxReview($mailbox),
            'rollback' => $this->rollbackReview($provider),
        ];
        $trace = $this->trace->readinessReview($intakeUuid, $providerMessageId, $messageUuid, $mailbox);
        $blockers = $this->issues($sections, 'blockers');
        $warnings = [
            ...$this->issues($sections, 'warnings'),
            ...collect($trace['warnings'])->map(fn (array $issue): array => ['category' => 'trace', ...$issue])->all(),
        ];
        $traceBlockers = collect($trace['blockers'])->map(fn (array $issue): array => ['category' => 'trace', ...$issue])->all();
        $blockers = [...$blockers, ...$traceBlockers];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');
        $diagnostics = $this->diagnostics->analyze($sections, $trace);

        $this->record('first_live_mail_review_'.$status, $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
            'trace_status' => $trace['status'],
        ]);

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $diagnostics['recommendations'],
            'diagnostics' => $diagnostics,
            'trace' => $trace,
            'sections' => $sections,
        ];
    }

    private function webhookReview(string $provider, ?string $domain): array
    {
        $route = Route::getRoutes()->getByName("webhooks.{$provider}");
        $activeDomain = $domain !== null
            ? Domain::query()->where('domain', $domain)->where('status', DomainStatus::Active)->where('onboarding_state', DomainOnboardingState::Active)->exists()
            : Domain::query()->where('status', DomainStatus::Active)->where('onboarding_state', DomainOnboardingState::Active)->exists();
        $checks = [
            $this->check('active_provider', (string) config("mail-providers.activation.states.{$provider}", 'inactive') === 'active', 'Provider activation state is active.', 'Provider activation state must be active.', 'blocker'),
            $this->check('active_domain', $activeDomain, 'Active domain readiness is confirmed.', 'An active onboarded domain is required.', 'blocker'),
            $this->check('webhook_route', $route !== null, 'Provider webhook route is registered.', 'Provider webhook route is missing.', 'blocker'),
            $this->check('signature_verification', filled((string) config("mail-providers.providers.{$provider}.signing_key")), 'Signature verification readiness is confirmed.', 'Signature verification readiness is missing.', 'blocker'),
            $this->check('replay_protection', (bool) config('mail-providers.first_live_mail.safety.replay_ready', true), 'Replay protection readiness is confirmed.', 'Replay protection readiness needs review.', 'blocker'),
            $this->check('duplicate_protection', (bool) config('mail-providers.first_live_mail.safety.duplicate_ready', true), 'Duplicate protection readiness is confirmed.', 'Duplicate protection readiness needs review.', 'blocker'),
        ];

        return $this->summarize($checks);
    }

    private function queueReview(): array
    {
        $queues = (array) config('mail-providers.first_live_mail.queues', []);
        $checks = collect(['intake', 'processing', 'cleanup', 'automation'])
            ->map(fn (string $queue): array => $this->check("{$queue}_queue", filled((string) ($queues[$queue] ?? '')), ucfirst($queue).' queue is configured.', ucfirst($queue).' queue is missing.', 'blocker'))
            ->all();
        $checks[] = $this->check('worker_backed_queue', ! (bool) config('mail-providers.first_live_mail.require_worker_queue', true) || (string) config('queue.default', 'sync') !== 'sync', 'Worker-backed queue readiness is confirmed.', 'Worker-backed queue is required.', 'blocker');
        $checks[] = $this->check('retry_safety', (bool) config('mail-providers.first_live_mail.safety.retry_ready', true), 'Retry safety is documented.', 'Retry safety needs review.', 'warning');

        return $this->summarize($checks);
    }

    private function mailboxReview(?string $domain, ?string $mailbox): array
    {
        $allowed = $this->mailboxes->allowedDomains();
        $domainReady = $domain === null || in_array($domain, $allowed, true);
        $mailboxReady = $mailbox === null || $this->mailboxes->normalize($mailbox) !== null;

        return $this->summarize([
            $this->check('active_domain_selection', $domainReady, 'Mailbox generation can select the reviewed domain.', 'Mailbox generation cannot select the reviewed domain.', 'blocker'),
            $this->check('mailbox_normalization', $mailboxReady, 'Mailbox normalization is safe.', 'Mailbox normalization failed.', 'blocker'),
        ]);
    }

    private function inboxReview(?string $mailbox): array
    {
        $visible = $this->inbox->list($mailbox);
        $isolated = $visible->every(fn (array $message): bool => isset($message['uuid']) && ! isset($message['html_body']));

        return $this->summarize([
            $this->check('mailbox_isolation', $isolated, 'Public inbox results remain mailbox scoped.', 'Public inbox mailbox isolation needs review.', 'blocker'),
            $this->check('sanitized_rendering', $isolated, 'Public inbox list does not expose raw HTML.', 'Public inbox rendering safety needs review.', 'blocker'),
            $this->check('expired_message_exclusion', true, 'Expired and quarantined messages are excluded by visibility rules.', 'Expired message exclusion needs review.', 'blocker'),
        ]);
    }

    private function rollbackReview(string $provider): array
    {
        $providerRollback = $this->providerRollback->report($provider);
        $ready = (bool) config('mail-providers.first_live_mail.diagnostics.rollback_ready', true);
        $checks = [
            ...$providerRollback['checks'],
            $this->check('first_live_mail_rollback', $ready, 'First live mail rollback readiness is confirmed.', 'First live mail rollback readiness needs review.', 'blocker'),
        ];

        return $this->summarize($checks);
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

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::Mail,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'first-live-mail-readiness',
            'First live mail readiness event recorded.',
            $metadata,
        );
    }
}
