<?php

namespace App\Services\Billing;

use App\Enums\AccountTier;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\Enterprise\TenantContextService;
use App\Services\Service;
use Throwable;

final class FeatureGateService extends Service
{
    public function __construct(
        private readonly TenantContextService $tenantContext,
    ) {}

    public function currentPlan(?User $user = null, ?Organization $organization = null): string
    {
        $organization ??= $this->contextOrganization($user);

        if ($organization instanceof Organization) {
            $plan = $organization->plan()->where('is_active', true)->value('slug');

            if (filled($plan)) {
                return $plan;
            }
        }

        if ($user === null) {
            return $this->defaultPlan();
        }

        try {
            return $user->activePlan()->value('slug')
                ?? $user->account_tier?->value
                ?? $this->defaultPlan();
        } catch (Throwable) {
            return $user->account_tier?->value ?? $this->defaultPlan();
        }
    }

    public function currentPlanModel(?User $user = null, ?Organization $organization = null): ?Plan
    {
        $organization ??= $this->contextOrganization($user);

        if ($organization instanceof Organization) {
            $plan = $organization->plan()->where('is_active', true)->first();

            if ($plan instanceof Plan) {
                return $plan;
            }
        }

        if ($user === null) {
            return null;
        }

        try {
            return $user->activePlan()->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function hasFeature(string $feature, ?User $user = null, ?Organization $organization = null): bool
    {
        return (bool) $this->featureValue($feature, $user, false, $organization);
    }

    public function featureValue(
        string $feature,
        ?User $user = null,
        mixed $fallback = null,
        ?Organization $organization = null,
    ): mixed {
        $plan = $this->currentPlan($user, $organization);
        $value = config("features-gates.plans.{$plan}.{$feature}");

        if ($value !== null) {
            return $value;
        }

        return config("features-gates.plans.{$this->defaultPlan()}.{$feature}", $fallback);
    }

    private function contextOrganization(?User $user = null): ?Organization
    {
        try {
            return $this->tenantContext->current(user: $user);
        } catch (Throwable) {
            return null;
        }
    }

    private function defaultPlan(): string
    {
        $plan = (string) config('features-gates.default_plan', AccountTier::Free->value);

        return AccountTier::tryFrom($plan)?->value ?? AccountTier::Free->value;
    }
}
