<?php

namespace App\Services\Billing\Providers;

use App\Contracts\Billing\BillingGatewayContract;

final class LocalBillingProvider implements BillingGatewayContract
{
    public function providerName(): string
    {
        return 'local';
    }

    public function verifyWebhook(string $payload, ?string $signature = null): bool
    {
        if (app()->environment('testing') && config('billing.providers.local.allow_unsigned_in_testing', false)) {
            return true;
        }

        $secret = (string) config('billing.providers.local.webhook_secret', 'local-testing-secret');
        $expected = hash_hmac('sha256', $payload, $secret);

        return is_string($signature) && hash_equals($expected, $signature);
    }

    public function normalizeWebhookPayload(array $payload): array
    {
        return [
            'event_id' => $payload['id'] ?? null,
            'event_type' => $payload['type'] ?? 'unknown',
            'customer' => $this->resolveCustomer($payload),
            'subscription' => $this->resolveSubscription($payload),
            'invoice' => $this->resolveInvoice($payload),
        ];
    }

    public function resolveCustomer(array $payload): array
    {
        return [
            'provider_customer_id' => data_get($payload, 'customer.id'),
            'email' => data_get($payload, 'customer.email'),
            'user_id' => data_get($payload, 'customer.user_id'),
            'organization_id' => data_get($payload, 'customer.organization_id'),
            'metadata' => data_get($payload, 'customer.metadata', []),
        ];
    }

    public function resolveSubscription(array $payload): ?array
    {
        if (! data_get($payload, 'subscription.id')) {
            return null;
        }

        return [
            'provider_subscription_id' => data_get($payload, 'subscription.id'),
            'provider_plan_id' => data_get($payload, 'subscription.plan'),
            'status' => data_get($payload, 'subscription.status', 'active'),
            'interval' => data_get($payload, 'subscription.interval'),
            'trial_ends_at' => data_get($payload, 'subscription.trial_ends_at'),
            'current_period_starts_at' => data_get($payload, 'subscription.current_period_starts_at'),
            'current_period_ends_at' => data_get($payload, 'subscription.current_period_ends_at'),
            'cancels_at' => data_get($payload, 'subscription.cancels_at'),
            'canceled_at' => data_get($payload, 'subscription.canceled_at'),
            'metadata' => data_get($payload, 'subscription.metadata', []),
        ];
    }

    public function resolveInvoice(array $payload): ?array
    {
        if (! data_get($payload, 'invoice.id')) {
            return null;
        }

        return [
            'provider_invoice_id' => data_get($payload, 'invoice.id'),
            'status' => data_get($payload, 'invoice.status', 'open'),
            'currency' => data_get($payload, 'invoice.currency'),
            'amount_due' => data_get($payload, 'invoice.amount_due'),
            'amount_paid' => data_get($payload, 'invoice.amount_paid'),
            'hosted_invoice_url' => data_get($payload, 'invoice.hosted_invoice_url'),
            'pdf_url' => data_get($payload, 'invoice.pdf_url'),
            'issued_at' => data_get($payload, 'invoice.issued_at'),
            'paid_at' => data_get($payload, 'invoice.paid_at'),
            'metadata' => data_get($payload, 'invoice.metadata', []),
        ];
    }
}
