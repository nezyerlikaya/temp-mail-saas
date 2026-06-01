<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Services\Service;

final class CustomerLifecycleReadinessService extends Service
{
    public function report(): array
    {
        $checks = [
            $this->check('customer_creation', (bool) config('billing.revenue_readiness.customer_lifecycle.customer_creation', true), 'Customer creation readiness is confirmed.', 'Customer creation readiness needs review.', 'blocker'),
            $this->check('subscription_assignment', (bool) config('billing.revenue_readiness.customer_lifecycle.subscription_assignment', true), 'Subscription assignment readiness is confirmed.', 'Subscription assignment readiness needs review.', 'blocker'),
            $this->check('plan_transition', (bool) config('billing.revenue_readiness.customer_lifecycle.plan_transition', true) && Plan::query()->whereIn('slug', ['free', 'member', 'premium'])->count() === 3, 'Plan transition readiness is confirmed.', 'Plan transition readiness needs review.', 'blocker'),
            $this->check('cancellation', (bool) config('billing.revenue_readiness.customer_lifecycle.cancellation', true), 'Cancellation readiness is confirmed.', 'Cancellation readiness needs review.', 'warning'),
            $this->check('renewal', (bool) config('billing.revenue_readiness.customer_lifecycle.renewal', true), 'Renewal readiness is confirmed.', 'Renewal readiness needs review.', 'warning'),
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
            'status' => collect($checks)->where('classification', 'blocker')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }
}
