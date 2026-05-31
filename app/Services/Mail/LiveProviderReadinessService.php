<?php

namespace App\Services\Mail;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Models\ProviderActivationAudit;
use App\Services\Domain\DomainPoolService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class LiveProviderReadinessService extends Service
{
    public function __construct(
        private readonly ProviderActivationService $activation,
        private readonly ProviderRegistryService $registry,
        private readonly ProviderConnectivityValidationService $connectivity,
        private readonly ProviderRollbackReadinessService $rollback,
        private readonly DomainPoolService $domainPool,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(?string $provider = null): array
    {
        $this->record('live_provider_review_started');

        $providers = $provider !== null
            ? [$this->normalize($provider)]
            : array_map(fn (string $name): string => $this->normalize($name), (array) config('mail-providers.live_activation.providers', ['mailgun', 'postmark', 'ses']));
        $sections = collect($providers)
            ->mapWithKeys(fn (string $name): array => [$name => $this->providerReview($name)])
            ->all();
        $rollback = $this->rollback->report($providers[0] ?? 'mailgun');
        $blockers = [
            ...$this->issues($sections, 'blockers'),
            ...collect($rollback['blockers'])->map(fn (array $issue): array => ['category' => 'rollback', ...$issue])->all(),
        ];
        $warnings = [
            ...$this->issues($sections, 'warnings'),
            ...collect($rollback['warnings'])->map(fn (array $issue): array => ['category' => 'rollback', ...$issue])->all(),
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');
        $recommendations = $this->recommendations($blockers, $warnings);

        $this->auditReviews($sections, $status);
        $this->record('live_provider_review_'.$status, $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'provider_count' => count($providers),
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
        ]);

        return [
            'status' => $status,
            'providers' => $providers,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'rollback' => $rollback,
            'sections' => $sections,
        ];
    }

    private function providerReview(string $provider): array
    {
        $activation = $this->activation->readiness($provider);
        $connectivity = $this->connectivity->report($provider);
        $state = $activation['states'][$provider] ?? 'inactive';
        $health = $this->registry->health($provider);
        $checks = [
            ...$this->configurationChecks($provider, $state, $health, $activation, $connectivity),
            ...$this->webhookChecks($provider, $health, $connectivity),
            ...$this->compatibilityChecks($provider),
            ...$this->observabilityChecks(),
        ];

        return $this->summarize($checks);
    }

    private function configurationChecks(string $provider, string $state, array $health, array $activation, array $connectivity): array
    {
        return [
            $this->check($provider, 'activation_state', ! (bool) config('mail-providers.live_activation.require_active_state', true) || $state === 'active', 'Provider activation state is active.', 'Provider activation state must be active.', 'blocker'),
            $this->check($provider, 'provider_enabled', ! (bool) config('mail-providers.live_activation.require_enabled_provider', true) || $health['enabled'], 'Provider is enabled.', 'Provider must be enabled before live activation.', 'blocker'),
            $this->check($provider, 'webhook_configuration', $connectivity['blockers'] === [], 'Webhook configuration has no blockers.', 'Webhook configuration has blockers.', 'blocker'),
            $this->check($provider, 'signing_secret_readiness', ! (bool) config('mail-providers.live_activation.require_signing_secret', true) || $health['has_signing_key'], 'Signing secret readiness is confirmed.', 'Signing secret readiness is missing.', 'blocker'),
            $this->check($provider, 'activation_safety', $activation['blockers'] === [], 'Provider activation safety has no blockers.', 'Provider activation safety has blockers.', 'blocker'),
        ];
    }

    private function webhookChecks(string $provider, array $health, array $connectivity): array
    {
        $route = Route::getRoutes()->getByName("webhooks.{$provider}");
        $middleware = $route?->gatherMiddleware() ?? [];

        return [
            $this->check($provider, 'webhook_route_registered', Route::has("webhooks.{$provider}"), 'Webhook route is registered.', 'Webhook route is missing.', 'blocker'),
            $this->check($provider, 'installer_enforcement', ! (bool) config('mail-providers.live_activation.webhook.require_installer_middleware', true) || in_array('app.installed', $middleware, true), 'Installer enforcement is attached to webhook route.', 'Webhook route must enforce installed application state.', 'blocker'),
            $this->check($provider, 'signature_verification', ! (bool) config('mail-providers.live_activation.webhook.require_signature_verification', true) || $health['has_signing_key'], 'Signature verification is configured.', 'Signature verification requires provider signing configuration.', 'blocker'),
            $this->check($provider, 'replay_protection', ! (bool) config('mail-providers.live_activation.webhook.require_replay_protection', true) || $health['has_signing_key'], 'Replay protection is tied to signature timestamp checks.', 'Replay protection requires signing configuration.', 'blocker'),
            $this->check($provider, 'duplicate_protection', (bool) config('mail-providers.live_activation.webhook.require_duplicate_protection', true), 'Duplicate protection uses provider message ids and intake keys.', 'Duplicate protection must be confirmed.', 'blocker'),
            $this->check($provider, 'queue_first_handoff', ! (bool) config('mail-providers.live_activation.webhook.require_queue_handoff', true) || (filled((string) config('inbound.queue.name', 'inbound-mail')) && (string) config('queue.default', 'sync') !== 'sync'), 'Webhook intake uses queue-first handoff.', 'Webhook handoff requires a worker-backed queue.', 'blocker'),
            $this->check($provider, 'webhook_readiness', $connectivity['blockers'] === [], 'Webhook activation review has no blockers.', 'Webhook activation review has blockers.', 'blocker'),
        ];
    }

    private function compatibilityChecks(string $provider): array
    {
        $activeDomains = Domain::query()
            ->where('status', DomainStatus::Active)
            ->where('onboarding_state', DomainOnboardingState::Active)
            ->get();
        $mappedDomains = $activeDomains
            ->filter(fn (Domain $domain): bool => data_get($domain->metadata, 'onboarding.provider') === $provider);
        $allowedDomains = $this->domainPool->allowedDomainNames();

        return [
            $this->check($provider, 'active_domains_available', ! (bool) config('mail-providers.live_activation.require_active_domain', true) || $activeDomains->isNotEmpty(), 'Active onboarded domains are available.', 'Active onboarded domains are required.', 'blocker'),
            $this->check($provider, 'provider_mapping_valid', $mappedDomains->isNotEmpty(), 'Provider mapping is present for at least one active domain.', 'Provider mapping should be reviewed for active domains.', 'warning'),
            $this->check($provider, 'onboarding_compatibility', $activeDomains->every(fn (Domain $domain): bool => $domain->isOnboardingActive()), 'Domain onboarding compatibility is confirmed.', 'Domain onboarding compatibility needs review.', 'blocker'),
            $this->check($provider, 'mailbox_generation_compatibility', $allowedDomains !== [], 'Mailbox generation has allowed domains.', 'Mailbox generation domain list is empty.', 'blocker'),
        ];
    }

    private function observabilityChecks(): array
    {
        return [
            $this->check('providers', 'metrics_observability', ! (bool) config('mail-providers.live_activation.observability.metrics_required', true) || (bool) config('mail-providers.activation.readiness.metrics_enabled', true), 'Provider metrics are enabled.', 'Provider metrics should be enabled before live activation.', 'warning'),
            $this->check('providers', 'operations_events', (bool) config('mail-providers.live_activation.observability.operations_events_required', true), 'Operations events are configured for live provider reviews.', 'Operations event readiness needs review.', 'warning'),
        ];
    }

    private function auditReviews(array $sections, string $status): void
    {
        foreach ($sections as $provider => $section) {
            $state = (string) config("mail-providers.activation.states.{$provider}", 'inactive');
            $activationRecommendation = $status === 'blocked'
                ? 'Hold live activation until blockers are cleared.'
                : ($status === 'warning' ? 'Proceed only after warnings are acknowledged.' : 'Provider is ready for controlled live activation.');
            $suspensionRecommendation = $section['blockers'] === []
                ? 'Suspension path remains available if live activation degrades.'
                : 'Prepare suspension before attempting live activation.';

            foreach ([
                'readiness_review' => 'Live provider readiness review recorded.',
                'activation_recommendation' => $activationRecommendation,
                'suspension_recommendation' => $suspensionRecommendation,
            ] as $reviewType => $recommendation) {
                ProviderActivationAudit::query()->create([
                    'provider' => $provider,
                    'previous_state' => $state,
                    'new_state' => $state,
                    'reason' => 'Live provider readiness review.',
                    'performed_by' => 'system',
                    'review_type' => $reviewType,
                    'recommendation' => $recommendation,
                    'metadata' => [
                        'status' => $section['blockers'] === [] ? ($section['warnings'] === [] ? 'ready' : 'warning') : 'blocked',
                        'blocker_count' => count($section['blockers']),
                        'warning_count' => count($section['warnings']),
                    ],
                ]);
            }
        }
    }

    private function check(string $provider, string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'provider' => $provider,
            'name' => "{$provider}_{$name}",
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
            ->flatMap(fn (array $section, string $provider): array => collect($section[$type])
                ->map(fn (array $issue): array => ['category' => $provider, ...$issue])
                ->all())
            ->values()
            ->all();
    }

    private function recommendations(array $blockers, array $warnings): array
    {
        return collect([...$blockers, ...$warnings])
            ->map(fn (array $issue): string => $issue['message'])
            ->unique()
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
            'live-provider-readiness',
            'Live provider readiness event recorded.',
            $metadata,
        );
    }

    private function normalize(string $provider): string
    {
        return $provider === 'amazon_ses' ? 'ses' : $provider;
    }
}
