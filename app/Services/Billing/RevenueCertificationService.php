<?php

namespace App\Services\Billing;

use App\Services\Service;

final class RevenueCertificationService extends Service
{
    public function __construct(
        private readonly CustomerLifecycleReadinessService $customers,
        private readonly SubscriptionOperationsService $subscriptions,
        private readonly PaymentIncidentReadinessService $incidents,
    ) {}

    public function certify(array $billing): array
    {
        $customers = $this->customers->report();
        $subscriptions = $this->subscriptions->review();
        $incidents = $this->incidents->report();
        $checks = [
            $this->check('billing_readiness', ! (bool) config('billing.revenue_readiness.certification.billing', true) || $billing['blockers'] === [], 'Billing readiness is certified.', 'Billing readiness is blocked.', 'blocked'),
            $this->check('subscription_readiness', ! (bool) config('billing.revenue_readiness.certification.subscription', true) || $subscriptions['blockers'] === [], 'Subscription readiness is certified.', 'Subscription readiness is blocked.', 'blocked'),
            $this->check('customer_lifecycle_readiness', ! (bool) config('billing.revenue_readiness.certification.customer_lifecycle', true) || $customers['blockers'] === [], 'Customer lifecycle readiness is certified.', 'Customer lifecycle readiness is blocked.', 'blocked'),
            $this->check('payment_incident_readiness', ! (bool) config('billing.revenue_readiness.certification.payment_incidents', true) || $incidents['blockers'] === [], 'Payment incident readiness is certified.', 'Payment incident readiness is blocked.', 'blocked'),
        ];
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = [
            ...$customers['warnings'],
            ...$subscriptions['warnings'],
            ...$incidents['warnings'],
        ];

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified'),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'checks' => $checks,
            'customers' => $customers,
            'subscriptions' => $subscriptions,
            'incidents' => $incidents,
        ];
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
}
