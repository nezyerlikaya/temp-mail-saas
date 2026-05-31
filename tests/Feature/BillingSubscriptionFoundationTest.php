<?php

namespace Tests\Feature;

use App\Enums\BillingSubscriptionStatus;
use App\Enums\BillingWebhookStatus;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\BillingWebhookEvent;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserPlanAssignment;
use App\Services\Billing\BillingService;
use App\Services\Billing\Providers\LocalBillingProvider;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BillingSubscriptionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        config(['billing.providers.local.webhook_secret' => 'test-secret']);
    }

    public function test_billing_migrations_work_and_do_not_include_card_fields(): void
    {
        foreach (['billing_customers', 'billing_subscriptions', 'billing_invoices', 'billing_webhook_events'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertFalse(Schema::hasColumn($table, 'card_number'));
            $this->assertFalse(Schema::hasColumn($table, 'card_last_four'));
            $this->assertFalse(Schema::hasColumn($table, 'payment_method_id'));
        }

        $this->assertTrue(Schema::hasColumns('billing_customers', ['provider', 'provider_customer_id']));
        $this->assertTrue(Schema::hasColumns('billing_subscriptions', ['provider_subscription_id', 'status']));
        $this->assertTrue(Schema::hasColumns('billing_invoices', ['provider_invoice_id', 'amount_due']));
        $this->assertTrue(Schema::hasColumns('billing_webhook_events', ['payload_hash', 'signature_valid']));
    }

    public function test_local_provider_verifies_signature_and_normalizes_payload(): void
    {
        $payload = $this->payload();
        $raw = json_encode($payload);
        $provider = app(LocalBillingProvider::class);

        $this->assertTrue($provider->verifyWebhook($raw, $this->signature($payload)));
        $this->assertFalse($provider->verifyWebhook($raw, 'bad-signature'));
        $this->assertSame('customer.subscription.updated', $provider->normalizeWebhookPayload($payload)['event_type']);
    }

    public function test_invalid_signature_is_rejected_without_processing(): void
    {
        $this->withHeader('X-Billing-Signature', 'bad-signature')
            ->postJson('/billing/webhooks/local', $this->payload())
            ->assertUnauthorized()
            ->assertJson(['ok' => false, 'status' => 'rejected']);

        $event = BillingWebhookEvent::query()->firstOrFail();

        $this->assertSame(BillingWebhookStatus::Rejected, $event->status);
        $this->assertFalse($event->signature_valid);
        $this->assertSame(0, BillingCustomer::query()->count());
    }

    public function test_webhook_idempotency_works(): void
    {
        $payload = $this->payload();

        $this->signedWebhook($payload)->assertOk();
        $this->signedWebhook($payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, BillingWebhookEvent::query()->count());
        $this->assertSame(1, BillingSubscription::query()->count());
    }

    public function test_subscription_active_creates_user_plan_assignment(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload(customer: ['user_id' => $user->id]);

        $this->signedWebhook($payload)->assertOk()->assertJson(['status' => 'processed']);

        $subscription = BillingSubscription::query()->firstOrFail();
        $assignment = UserPlanAssignment::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(BillingSubscriptionStatus::Active, $subscription->status);
        $this->assertSame('premium', $assignment->plan->slug);
        $this->assertTrue($assignment->isActive());
    }

    public function test_organization_subscription_updates_organization_plan(): void
    {
        $organization = Organization::query()->create([
            'uuid' => fake()->uuid(),
            'name' => 'Billing Org',
            'slug' => 'billing-org',
            'status' => 'active',
        ]);
        $payload = $this->payload(customer: ['organization_id' => $organization->id]);

        $this->signedWebhook($payload)->assertOk();

        $this->assertSame('premium', $organization->fresh()->plan->slug);
    }

    public function test_cancellation_updates_subscription_lifecycle_safely(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload(
            customer: ['user_id' => $user->id],
            subscription: [
                'status' => 'canceled',
                'canceled_at' => now()->toIso8601String(),
            ],
        );

        $this->signedWebhook($payload)->assertOk();

        $subscription = BillingSubscription::query()->firstOrFail();

        $this->assertSame(BillingSubscriptionStatus::Canceled, $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertSame(0, UserPlanAssignment::query()->count());
    }

    public function test_invoice_metadata_is_stored_without_card_data(): void
    {
        $payload = $this->payload(invoice: [
            'metadata' => [
                'safe' => 'value',
                'card_token' => 'hidden',
            ],
        ]);

        $this->signedWebhook($payload)->assertOk();

        $invoice = BillingInvoice::query()->firstOrFail();

        $this->assertSame(['safe' => 'value'], $invoice->metadata);
        $this->assertStringNotContainsString('hidden', $invoice->toJson());
    }

    public function test_billing_service_sanitizes_customer_metadata(): void
    {
        $customer = app(BillingService::class)->createOrUpdateCustomer('local', [
            'provider_customer_id' => 'cus_manual',
            'metadata' => [
                'safe' => 'value',
                'payment_method' => 'hidden',
            ],
        ]);

        $this->assertSame(['safe' => 'value'], $customer->metadata);
    }

    public function test_existing_routes_still_work(): void
    {
        $this->getJson('/api/v1/ping')->assertUnauthorized();
        $this->get('/login')->assertOk();
        $this->get('/inbox')->assertOk();
        $this->assertContains($this->get('/install')->getStatusCode(), [200, 302]);
        $this->get('/admin')->assertForbidden();
    }

    private function signedWebhook(array $payload): TestResponse
    {
        return $this->withHeader('X-Billing-Signature', $this->signature($payload))
            ->postJson('/billing/webhooks/local', $payload);
    }

    private function signature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), 'test-secret');
    }

    private function payload(array $customer = [], array $subscription = [], array $invoice = []): array
    {
        return [
            'id' => 'evt_local_1',
            'type' => 'customer.subscription.updated',
            'customer' => array_merge([
                'id' => 'cus_local_1',
                'email' => 'billing@example.test',
                'metadata' => ['source' => 'test'],
            ], $customer),
            'subscription' => array_merge([
                'id' => 'sub_local_1',
                'plan' => 'local_premium',
                'status' => 'active',
                'interval' => 'month',
                'current_period_starts_at' => now()->toIso8601String(),
                'current_period_ends_at' => now()->addMonth()->toIso8601String(),
                'metadata' => ['safe' => 'subscription'],
            ], $subscription),
            'invoice' => array_merge([
                'id' => 'inv_local_1',
                'status' => 'paid',
                'currency' => 'usd',
                'amount_due' => 1000,
                'amount_paid' => 1000,
                'hosted_invoice_url' => 'https://example.test/invoice',
                'pdf_url' => 'https://example.test/invoice.pdf',
                'issued_at' => now()->toIso8601String(),
                'paid_at' => now()->toIso8601String(),
                'metadata' => ['safe' => 'invoice'],
            ], $invoice),
        ];
    }
}
