<?php

namespace App\Services\Billing;

use App\Services\Service;

final class SubscriptionOperationsService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('subscription_activation', (bool) config('billing.revenue_readiness.subscription_operations.activation', true), 'Subscription activation review is ready.', 'Subscription activation review needs attention.', 'blocker'),
            $this->check('downgrade_review', (bool) config('billing.revenue_readiness.subscription_operations.downgrade', true), 'Downgrade review is ready.', 'Downgrade review needs attention.', 'warning'),
            $this->check('upgrade_review', (bool) config('billing.revenue_readiness.subscription_operations.upgrade', true), 'Upgrade review is ready.', 'Upgrade review needs attention.', 'warning'),
            $this->check('cancellation_review', (bool) config('billing.revenue_readiness.subscription_operations.cancellation', true), 'Cancellation review is ready.', 'Cancellation review needs attention.', 'warning'),
            $this->check('invoice_review', (bool) config('billing.revenue_readiness.subscription_operations.invoice_review', true), 'Invoice review is ready.', 'Invoice review needs attention.', 'warning'),
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
