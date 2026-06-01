<?php

namespace Tests\Feature;

use App\Enums\BillingProvider;
use App\Enums\BillingWebhookStatus;
use App\Models\BillingWebhookEvent;
use App\Services\Billing\CustomerLifecycleReadinessService;
use App\Services\Billing\PaymentIncidentReadinessService;
use App\Services\Billing\RevenueCertificationService;
use App\Services\Billing\RevenueReadinessService;
use App\Services\Billing\SubscriptionOperationsService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevenueActivationReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        config([
            'billing.enabled' => true,
            'billing.default_provider' => 'local',
            'billing.providers.local.webhook_secret' => 'revenue-secret',
            'billing.revenue_readiness.provider' => 'local',
        ]);
    }

    public function test_revenue_readiness_service_reports_ready_state(): void
    {
        $report = app(RevenueReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame('certified', $report['certification']['status']);
        $this->assertArrayHasKey('billing', $report['sections']);
        $this->assertArrayHasKey('plan', $report['sections']);
        $this->assertArrayHasKey('webhook', $report['sections']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'revenue_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'revenue_review_ready']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'revenue_certified']);
    }

    public function test_customer_lifecycle_readiness_can_warn_on_renewal_gap(): void
    {
        config(['billing.revenue_readiness.customer_lifecycle.renewal' => false]);

        $report = app(CustomerLifecycleReadinessService::class)->report();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('renewal', array_column($report['warnings'], 'name'));
    }

    public function test_subscription_operations_review_can_warn_without_blocking(): void
    {
        config(['billing.revenue_readiness.subscription_operations.downgrade' => false]);

        $report = app(SubscriptionOperationsService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('downgrade_review', array_column($report['warnings'], 'name'));
    }

    public function test_payment_incident_readiness_reports_webhook_failures_safely(): void
    {
        BillingWebhookEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => BillingProvider::Local,
            'event_type' => 'payment.failed',
            'signature_valid' => false,
            'status' => BillingWebhookStatus::Failed,
            'payload_hash' => hash('sha256', 'safe-payload'),
            'error_message' => 'Failed',
            'failed_at' => now(),
        ]);

        $report = app(PaymentIncidentReadinessService::class)->report();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('recent_webhook_failures', array_column($report['warnings'], 'name'));
        $this->assertStringNotContainsString('safe-payload', json_encode($report));
    }

    public function test_revenue_certification_blocks_when_billing_is_blocked(): void
    {
        $billing = ['blockers' => [['name' => 'billing_enabled', 'message' => 'Billing disabled.']]];

        $report = app(RevenueCertificationService::class)->certify($billing);

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('billing_readiness', array_column($report['blockers'], 'name'));
    }

    public function test_revenue_command_outputs_safe_summary(): void
    {
        $this->artisan('system:revenue-status')
            ->expectsOutput('Revenue readiness: READY')
            ->expectsOutput('Certification: CERTIFIED')
            ->doesntExpectOutputToContain('revenue-secret')
            ->doesntExpectOutputToContain('card')
            ->assertSuccessful();
    }

    public function test_revenue_command_fails_when_billing_is_disabled(): void
    {
        config(['billing.enabled' => false]);

        $this->artisan('system:revenue-status')
            ->expectsOutput('Revenue readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: billing.billing_enabled')
            ->doesntExpectOutputToContain('revenue-secret')
            ->assertFailed();
    }

    public function test_revenue_readiness_keeps_payment_data_out_of_schema_and_output(): void
    {
        foreach (['billing_customers', 'billing_subscriptions', 'billing_invoices', 'billing_webhook_events'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'card_number'));
            $this->assertFalse(Schema::hasColumn($table, 'card_last_four'));
            $this->assertFalse(Schema::hasColumn($table, 'payment_method_id'));
        }

        $encoded = json_encode(app(RevenueReadinessService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('revenue-secret', $encoded);
        $this->assertStringNotContainsString('payment_method_id', $encoded);
    }
}
