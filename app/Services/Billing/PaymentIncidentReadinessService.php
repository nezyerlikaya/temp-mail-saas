<?php

namespace App\Services\Billing;

use App\Enums\BillingWebhookStatus;
use App\Models\BillingWebhookEvent;
use App\Services\Service;

final class PaymentIncidentReadinessService extends Service
{
    public function report(): array
    {
        $failedEvents = BillingWebhookEvent::query()
            ->whereIn('status', [BillingWebhookStatus::Failed->value, BillingWebhookStatus::Rejected->value])
            ->count();
        $checks = [
            $this->check('webhook_failure_readiness', (bool) config('billing.revenue_readiness.incidents.webhook_failure', true), 'Webhook failure readiness is confirmed.', 'Webhook failure readiness needs review.', 'blocker'),
            $this->check('invoice_failure_readiness', (bool) config('billing.revenue_readiness.incidents.invoice_failure', true), 'Invoice failure readiness is confirmed.', 'Invoice failure readiness needs review.', 'warning'),
            $this->check('subscription_mismatch_readiness', (bool) config('billing.revenue_readiness.incidents.subscription_mismatch', true), 'Subscription mismatch readiness is confirmed.', 'Subscription mismatch readiness needs review.', 'warning'),
            $this->check('rollback_readiness', (bool) config('billing.revenue_readiness.incidents.rollback', true), 'Billing rollback readiness is confirmed.', 'Billing rollback readiness needs review.', 'blocker'),
            $this->check('recent_webhook_failures', $failedEvents === 0, 'No billing webhook failures require immediate review.', 'Billing webhook failures require review.', 'warning'),
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
