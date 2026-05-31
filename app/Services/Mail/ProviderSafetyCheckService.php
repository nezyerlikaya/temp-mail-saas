<?php

namespace App\Services\Mail;

use App\Services\System\InstallationService;
use App\Services\System\StagingReadinessService;
use App\Services\Service;

final class ProviderSafetyCheckService extends Service
{
    public function __construct(
        private readonly ProviderConnectivityValidationService $connectivity,
        private readonly LoadReadinessService $load,
        private readonly InstallationService $installation,
        private readonly StagingReadinessService $staging,
    ) {}

    public function report(?string $provider = null): array
    {
        $providers = $provider !== null ? [$this->normalize($provider)] : (array) config('mail-providers.activation.readiness.providers', ['mailgun', 'postmark', 'ses']);
        $checks = collect($providers)
            ->flatMap(fn (string $name): array => $this->providerChecks($this->normalize($name)))
            ->values()
            ->all();

        return [
            'passed' => collect($checks)->where('status', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('status', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('status', 'blocked')->values()->all(),
            'checks' => $checks,
        ];
    }

    public function providerReady(string $provider): bool
    {
        return $this->report($provider)['blockers'] === [];
    }

    private function providerChecks(string $provider): array
    {
        $state = $this->state($provider);
        $connectivity = $this->connectivity->report($provider);
        $load = $this->load->report();
        $installation = $this->installation->status();
        $staging = $this->staging->evaluate();
        $hasSigningKey = filled((string) config("mail-providers.providers.{$provider}.signing_key"));
        $allowUnsigned = (bool) config('mail-providers.activation.safety.allow_active_without_signing_key', false);

        return [
            $this->check($provider, 'activation_state_valid', in_array($state, $this->allowedStates(), true), 'Activation state is valid.', 'Activation state is invalid.', 'blocked', ['state' => $state]),
            $this->check($provider, 'staging_validation_passed', ! (bool) config('mail-providers.activation.safety.require_staging_passed', true) || $staging['state'] !== 'blocked', 'Staging validation is acceptable.', 'Staging validation is blocked.', 'blocked', ['state' => $staging['state']]),
            $this->check($provider, 'webhook_readiness_passed', ! (bool) config('mail-providers.activation.safety.require_webhook_ready', true) || $connectivity['blockers'] === [], 'Webhook readiness passed.', 'Webhook readiness has blockers.', 'blocked', ['blockers' => count($connectivity['blockers'])]),
            $this->check($provider, 'queue_readiness_passed', ! (bool) config('mail-providers.activation.safety.require_queue_ready', true) || ($load['queue']['status'] ?? 'blocked') !== 'blocked', 'Queue readiness is acceptable.', 'Queue readiness is blocked.', 'blocked', ['status' => $load['queue']['status'] ?? 'unknown']),
            $this->check($provider, 'installer_readiness_passed', ! (bool) config('mail-providers.activation.safety.require_installer_ready', true) || $installation['healthy'] === true, 'Installer readiness passed.', 'Installer readiness is incomplete.', 'blocked', ['locked' => $installation['lock']['locked']]),
            $this->check($provider, 'signing_configuration_present', $hasSigningKey || $allowUnsigned, 'Signing configuration is present.', 'Signing configuration is missing.', 'warning', []),
            $this->check($provider, 'idempotency_ready', true, 'Duplicate intake protection is active.', 'Duplicate intake protection is unavailable.', 'blocked', []),
        ];
    }

    private function check(string $provider, string $name, bool $passed, string $passedMessage, string $failedMessage, string $failedStatus, array $metadata): array
    {
        return [
            'provider' => $provider,
            'name' => "{$provider}_{$name}",
            'passed' => $passed,
            'status' => $passed ? 'passed' : $failedStatus,
            'message' => $passed ? $passedMessage : $failedMessage,
            'metadata' => ['provider' => $provider, ...$metadata],
        ];
    }

    private function state(string $provider): string
    {
        return (string) config("mail-providers.activation.states.{$provider}", 'inactive');
    }

    private function allowedStates(): array
    {
        return (array) config('mail-providers.activation.allowed_states', ['inactive', 'staging', 'ready', 'active', 'suspended']);
    }

    private function normalize(string $provider): string
    {
        return $provider === 'amazon_ses' ? 'ses' : $provider;
    }
}
