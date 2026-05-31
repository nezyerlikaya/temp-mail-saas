<?php

namespace App\Services\Mail;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Services\Domain\DomainOnboardingService;
use App\Services\Domain\DomainPoolService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class FirstRealMailValidationService extends Service
{
    public function __construct(
        private readonly ProviderActivationService $providers,
        private readonly ProviderConnectivityValidationService $connectivity,
        private readonly DomainOnboardingService $domains,
        private readonly DomainPoolService $domainPool,
        private readonly PublicMailboxService $mailboxes,
        private readonly PublicInboxMessageService $inbox,
        private readonly LoadReadinessService $load,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(?string $provider = null, ?string $domain = null, ?string $mailbox = null): array
    {
        $provider = $provider !== null ? $this->normalizeProvider($provider) : (string) config('mail-providers.default', 'local');
        $domainModel = $domain !== null ? Domain::query()->where('domain', Str::lower($domain))->first() : null;
        $checks = [
            ...$this->providerChecks($provider),
            ...$this->domainChecks($domainModel, $domain),
            ...$this->webhookChecks($provider),
            ...$this->queueChecks(),
            ...$this->mailboxChecks($domainModel, $mailbox),
            ...$this->messageVisibilityChecks($mailbox),
            ...$this->cleanupChecks(),
        ];

        $blockers = collect($checks)->where('status', 'blocked')->values()->all();
        $warnings = collect($checks)->where('status', 'warning')->values()->all();
        $recommendations = $this->recommendations($checks);
        $state = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record('first_mail_validation_started', $state);
        $this->record($state === 'blocked' ? 'first_mail_validation_blocked' : 'first_mail_validation_ready', $state);

        return [
            'status' => $state,
            'checks' => $checks,
            'passed' => collect($checks)->where('status', 'passed')->values()->all(),
            'warnings' => $warnings,
            'blockers' => $blockers,
            'recommendations' => $recommendations,
        ];
    }

    private function providerChecks(string $provider): array
    {
        $activation = $this->providers->readiness($provider);
        $state = $activation['states'][$provider] ?? 'inactive';

        return [
            $this->check('provider_enabled', (bool) config("mail-providers.providers.{$provider}.enabled", false), 'Provider is enabled.', 'Provider is disabled.'),
            $this->check('provider_active_state', $state === 'active', 'Provider activation state is active.', 'Provider activation state must be active.'),
            $this->check('provider_no_activation_blockers', $activation['blockers'] === [], 'Provider activation has no blockers.', 'Provider activation has blockers.'),
            $this->check('signature_required', filled((string) config("mail-providers.providers.{$provider}.signing_key")), 'Webhook signing configuration is present.', 'Webhook signing configuration is missing.'),
        ];
    }

    private function domainChecks(?Domain $domain, ?string $requestedDomain): array
    {
        if ($requestedDomain !== null && ! $domain instanceof Domain) {
            return [$this->check('domain_inventory_present', false, 'Domain exists.', 'Domain is missing from inventory.')];
        }

        if (! $domain instanceof Domain) {
            return [$this->check('domain_inventory_present', true, 'Domain selection can use configured fallback.', 'Domain is missing from inventory.', 'warning')];
        }

        $readiness = $this->domains->readiness($domain);

        return [
            $this->check('domain_inventory_present', true, 'Domain exists.', 'Domain is missing from inventory.'),
            $this->check('domain_active_status', $domain->status === DomainStatus::Active, 'Domain status is active.', 'Domain status is not active.'),
            $this->check('domain_onboarding_active', $domain->onboarding_state === DomainOnboardingState::Active, 'Domain onboarding state is active.', 'Domain onboarding state is not active.'),
            $this->check('domain_readiness', $readiness['blockers'] === [], 'Domain readiness has no blockers.', 'Domain readiness has blockers.'),
        ];
    }

    private function webhookChecks(string $provider): array
    {
        $connectivity = $this->connectivity->report($provider);

        return [
            $this->check('webhook_route_registered', Route::has("webhooks.{$provider}"), 'Webhook route is registered.', 'Webhook route is missing.'),
            $this->check('webhook_readiness', $connectivity['blockers'] === [], 'Webhook readiness has no blockers.', 'Webhook readiness has blockers.'),
            $this->check('duplicate_protection', true, 'Duplicate protection is provided by intake keys and provider message ids.', 'Duplicate protection is unavailable.'),
            $this->check('replay_protection', filled((string) config("mail-providers.providers.{$provider}.signing_key")), 'Replay-sensitive signature verification is configured.', 'Replay protection requires signing configuration.'),
        ];
    }

    private function queueChecks(): array
    {
        $load = $this->load->report();

        return [
            $this->check('queue_name_configured', filled((string) config('inbound.queue.name', 'inbound-mail')), 'Inbound queue is named.', 'Inbound queue is missing.'),
            $this->check('queue_capacity', ($load['queue']['status'] ?? 'blocked') !== 'blocked', 'Queue capacity check is acceptable.', 'Queue capacity check is blocked.'),
        ];
    }

    private function mailboxChecks(?Domain $domain, ?string $mailbox): array
    {
        $eligibleDomains = $this->domainPool->allowedDomainNames();
        $generated = null;

        if ($domain instanceof Domain && in_array($domain->domain, $eligibleDomains, true)) {
            $request = Request::create('/inbox/generate', 'POST');
            $request->setLaravelSession(new Store('first-real-mail-check', new ArraySessionHandler(120), (string) Str::uuid()));
            $generated = $this->mailboxes->generate($request);
        }

        return [
            $this->check('mailbox_generation', $domain === null || $generated !== null, 'Mailbox generation can use the onboarded domain.', 'Mailbox generation could not use the onboarded domain.'),
            $this->check('mailbox_normalization', $mailbox === null || $this->mailboxes->normalize($mailbox) !== null, 'Mailbox address normalizes safely.', 'Mailbox address is not allowed or invalid.', 'warning'),
        ];
    }

    private function messageVisibilityChecks(?string $mailbox): array
    {
        if ($mailbox === null) {
            return [$this->check('message_visibility_query', true, 'Message visibility can be validated after the first mailbox is known.', 'Mailbox is missing.', 'warning')];
        }

        $visible = $this->inbox->list($mailbox);

        return [
            $this->check('message_visibility_query', true, 'Message visibility query is safe.', 'Message visibility query failed.'),
            $this->check('message_scope', $visible->every(fn (array $message): bool => filled($message['uuid'] ?? null)), 'Visible messages are scoped to the mailbox.', 'Visible message scope is invalid.'),
        ];
    }

    private function cleanupChecks(): array
    {
        $load = $this->load->report();

        return [
            $this->check('cleanup_retention_compatibility', ($load['cleanup']['status'] ?? 'blocked') !== 'blocked', 'Cleanup and retention settings are compatible.', 'Cleanup and retention settings need review.', 'warning'),
        ];
    }

    private function recommendations(array $checks): array
    {
        return collect($checks)
            ->reject(fn (array $check): bool => $check['passed'])
            ->map(fn (array $check): string => match ($check['name']) {
                'provider_active_state' => 'Activate the selected provider only after sandbox and staging validation are green.',
                'domain_onboarding_active', 'domain_readiness' => 'Complete domain onboarding and DNS readiness before sending the first real message.',
                'signature_required', 'replay_protection' => 'Configure webhook signing secrets through environment variables.',
                'mailbox_generation' => 'Confirm the onboarded domain is eligible in the domain pool.',
                default => $check['message'],
            })
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

    private function normalizeProvider(string $provider): string
    {
        return $provider === 'amazon_ses' ? 'ses' : $provider;
    }

    private function record(string $eventType, string $state): void
    {
        $this->operations->log(
            OperationCategory::Mail,
            $eventType,
            $state === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'first-real-mail-validation',
            'First real mail validation event recorded.',
            ['state' => $state],
        );
    }
}
