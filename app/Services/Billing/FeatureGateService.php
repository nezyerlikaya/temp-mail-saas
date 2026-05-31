<?php

namespace App\Services\Billing;

use App\Enums\AccountTier;
use App\Models\Plan;
use App\Models\User;
use App\Services\Service;
use Throwable;

final class FeatureGateService extends Service
{
    public function currentPlan(?User $user = null): string
    {
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

    public function currentPlanModel(?User $user = null): ?Plan
    {
        if ($user === null) {
            return null;
        }

        try {
            return $user->activePlan()->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function hasFeature(string $feature, ?User $user = null): bool
    {
        return (bool) $this->featureValue($feature, $user, false);
    }

    public function featureValue(string $feature, ?User $user = null, mixed $fallback = null): mixed
    {
        $plan = $this->currentPlan($user);
        $value = config("features-gates.plans.{$plan}.{$feature}");

        if ($value !== null) {
            return $value;
        }

        return config("features-gates.plans.{$this->defaultPlan()}.{$feature}", $fallback);
    }

    private function defaultPlan(): string
    {
        $plan = (string) config('features-gates.default_plan', AccountTier::Free->value);

        return AccountTier::tryFrom($plan)?->value ?? AccountTier::Free->value;
    }
}
