<?php

namespace App\Services\Billing;

use App\Enums\BillingSubscriptionStatus;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\UserPlanAssignment;
use App\Services\Service;
use Illuminate\Support\Str;

final class BillingService extends Service
{
    public function createOrUpdateCustomer(string $provider, array $data): BillingCustomer
    {
        return BillingCustomer::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_customer_id' => $data['provider_customer_id'],
            ],
            [
                'uuid' => BillingCustomer::query()
                    ->where('provider', $provider)
                    ->where('provider_customer_id', $data['provider_customer_id'])
                    ->value('uuid') ?: (string) Str::uuid(),
                'user_id' => $data['user_id'] ?? null,
                'organization_id' => $data['organization_id'] ?? null,
                'email' => $data['email'] ?? null,
                'metadata' => $this->sanitizeMetadata($data['metadata'] ?? []),
            ],
        );
    }

    public function createOrUpdateSubscription(BillingCustomer $customer, string $provider, array $data): BillingSubscription
    {
        $plan = $this->resolvePlan($data['provider_plan_id'] ?? null);
        $subscription = BillingSubscription::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_subscription_id' => $data['provider_subscription_id'],
            ],
            [
                'uuid' => BillingSubscription::query()
                    ->where('provider', $provider)
                    ->where('provider_subscription_id', $data['provider_subscription_id'])
                    ->value('uuid') ?: (string) Str::uuid(),
                'billing_customer_id' => $customer->id,
                'plan_id' => $plan?->id,
                'status' => $data['status'] ?? BillingSubscriptionStatus::Active->value,
                'interval' => $data['interval'] ?? null,
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                'current_period_starts_at' => $data['current_period_starts_at'] ?? null,
                'current_period_ends_at' => $data['current_period_ends_at'] ?? null,
                'cancels_at' => $data['cancels_at'] ?? null,
                'canceled_at' => $data['canceled_at'] ?? null,
                'metadata' => $this->sanitizeMetadata($data['metadata'] ?? []),
            ],
        );

        $this->syncPlanAssignment($customer, $subscription);

        return $subscription->refresh();
    }

    public function createOrUpdateInvoice(BillingCustomer $customer, string $provider, array $data): BillingInvoice
    {
        return BillingInvoice::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_invoice_id' => $data['provider_invoice_id'],
            ],
            [
                'uuid' => BillingInvoice::query()
                    ->where('provider', $provider)
                    ->where('provider_invoice_id', $data['provider_invoice_id'])
                    ->value('uuid') ?: (string) Str::uuid(),
                'billing_customer_id' => $customer->id,
                'status' => $data['status'] ?? 'open',
                'currency' => $data['currency'] ?? null,
                'amount_due' => $data['amount_due'] ?? null,
                'amount_paid' => $data['amount_paid'] ?? null,
                'hosted_invoice_url' => $data['hosted_invoice_url'] ?? null,
                'pdf_url' => $data['pdf_url'] ?? null,
                'metadata' => $this->sanitizeMetadata($data['metadata'] ?? []),
                'issued_at' => $data['issued_at'] ?? null,
                'paid_at' => $data['paid_at'] ?? null,
            ],
        );
    }

    public function syncPlanAssignment(BillingCustomer $customer, BillingSubscription $subscription): void
    {
        if (! $subscription->plan_id) {
            return;
        }

        if ($customer->organization_id !== null) {
            Organization::query()
                ->whereKey($customer->organization_id)
                ->update(['plan_id' => $subscription->plan_id]);

            return;
        }

        if ($customer->user_id === null) {
            return;
        }

        if ($subscription->isActive()) {
            UserPlanAssignment::query()->updateOrCreate(
                [
                    'user_id' => $customer->user_id,
                    'plan_id' => $subscription->plan_id,
                ],
                [
                    'starts_at' => $subscription->current_period_starts_at ?? now(),
                    'expires_at' => $subscription->current_period_ends_at,
                    'notes' => 'Billing subscription assignment.',
                ],
            );
        }
    }

    public function resolvePlan(?string $providerPlanId): ?Plan
    {
        $slug = $providerPlanId !== null ? config("billing.provider_plan_map.{$providerPlanId}") : null;

        return $slug !== null ? Plan::query()->where('slug', $slug)->first() : null;
    }

    public function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => str_contains(Str::lower((string) $key), 'card')
                || str_contains(Str::lower((string) $key), 'payment_method')
                || str_contains(Str::lower((string) $key), 'secret')
                || str_contains(Str::lower((string) $key), 'token'))
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->sanitizeMetadata($value) : $value)
            ->all();
    }
}
