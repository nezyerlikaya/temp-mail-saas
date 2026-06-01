<?php

namespace App\Services\Enterprise;

use App\Services\Service;

final class OrganizationLifecycleService extends Service
{
    public function review(): array
    {
        return $this->summarize([
            $this->check('onboarding_readiness', (bool) config('enterprise.readiness.lifecycle.onboarding_ready', true), 'Organization onboarding readiness is available.', 'Organization onboarding readiness needs review.', 'warning'),
            $this->check('growth_readiness', (bool) config('enterprise.readiness.lifecycle.growth_ready', true), 'Organization growth readiness is available.', 'Organization growth readiness needs review.', 'warning'),
            $this->check('billing_readiness', (bool) config('enterprise.readiness.lifecycle.billing_ready', true), 'Organization billing readiness is available.', 'Organization billing readiness needs review.', 'warning'),
            $this->check('suspension_readiness', (bool) config('enterprise.readiness.lifecycle.suspension_ready', true), 'Organization suspension readiness is available.', 'Organization suspension readiness needs review.', 'blocked'),
            $this->check('recovery_readiness', (bool) config('enterprise.readiness.lifecycle.recovery_ready', true), 'Organization recovery readiness is available.', 'Organization recovery readiness needs review.', 'warning'),
        ]);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocked')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocked')->values()->all(),
            'checks' => $checks,
        ];
    }
}
