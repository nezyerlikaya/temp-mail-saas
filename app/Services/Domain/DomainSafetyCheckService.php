<?php

namespace App\Services\Domain;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\FeatureGateService;
use App\Services\Service;
use Illuminate\Support\Str;

final class DomainSafetyCheckService extends Service
{
    public function __construct(
        private readonly DomainDnsReadinessService $dns,
        private readonly FeatureGateService $features,
    ) {}

    public function report(Domain $domain, ?User $user = null, ?Organization $organization = null): array
    {
        $dns = $this->dns->review($domain);
        $provider = $this->dns->provider($domain);
        $checks = [
            $this->check('dns_readiness', $dns['ready'], 'DNS readiness has been manually confirmed.', 'DNS readiness confirmation is incomplete.'),
            $this->check('provider_compatibility', $this->providerCompatible($provider), 'Provider mapping is compatible.', 'Provider mapping is not ready for activation.'),
            $this->check('domain_pool_compatibility', $this->poolCompatible($domain), 'Domain pool compatibility passed.', 'Domain pool compatibility is blocked.'),
            $this->check('feature_gate_compatibility', $this->featureGateCompatible($domain, $user, $organization), 'Feature gate compatibility passed.', 'Domain tier is not compatible with the selected feature gates.'),
            $this->check('organization_compatibility', $organization?->isActive() ?? true, 'Organization compatibility passed.', 'Organization is not active.'),
        ];

        if ((bool) config('domains.onboarding.safety.warn_on_test_domain', true) && Str::endsWith($domain->domain, '.test')) {
            $checks[] = $this->check('test_domain_warning', false, '', 'Test domains must not be activated for production traffic.', 'warning');
        }

        $blockers = collect($checks)->where('status', 'blocked')->values()->all();
        $warnings = collect($checks)->where('status', 'warning')->values()->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'passed'),
            'passed' => collect($checks)->where('status', 'passed')->values()->all(),
            'warnings' => $warnings,
            'blockers' => $blockers,
            'recommendations' => $this->recommendations($dns, $checks),
            'checks' => $checks,
            'dns' => $dns,
        ];
    }

    public function activationReview(Domain $domain, ?User $user = null, ?Organization $organization = null): array
    {
        return $this->report($domain, $user, $organization);
    }

    private function providerCompatible(string $provider): bool
    {
        $state = (string) config("mail-providers.activation.states.{$provider}", 'inactive');

        return (bool) config("mail-providers.providers.{$provider}.enabled", false)
            && in_array($state, (array) config('domains.onboarding.provider_mapping.compatible_states', ['ready', 'active']), true);
    }

    private function poolCompatible(Domain $domain): bool
    {
        return $domain->status !== DomainStatus::Suspended
            && $domain->onboarding_state !== DomainOnboardingState::Suspended
            && $domain->health_score >= (int) config('domains.onboarding.safety.minimum_health_score', 80);
    }

    private function featureGateCompatible(Domain $domain, ?User $user, ?Organization $organization): bool
    {
        if ($user !== null || $organization !== null) {
            $tiers = $this->features->featureValue('domain_tiers', $user, [], $organization);

            return is_array($tiers) && in_array($domain->tier->value, $tiers, true);
        }

        return collect(config('domains-pool.tier_mapping', []))
            ->flatten()
            ->contains($domain->tier->value);
    }

    private function recommendations(array $dns, array $checks): array
    {
        $recommendations = collect($dns['pending'])
            ->map(fn (array $check): string => 'Confirm '.strtoupper($check['name']).' readiness manually.')
            ->all();

        if (collect($checks)->contains(fn (array $check): bool => $check['name'] === 'provider_compatibility' && ! $check['passed'])) {
            $recommendations[] = 'Confirm provider mapping and provider activation readiness.';
        }

        if (collect($checks)->contains(fn (array $check): bool => $check['name'] === 'domain_pool_compatibility' && ! $check['passed'])) {
            $recommendations[] = 'Review domain health score and suspended state before activation.';
        }

        return array_values(array_unique($recommendations));
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
}
