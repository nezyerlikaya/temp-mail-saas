<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\EnterpriseBillingGovernanceSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnterpriseBillingGovernanceSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_billing_governance_config_is_readable(): void
    {
        $this->assertIsArray(config('enterprise-billing-governance.billing_domains'));
        $this->assertIsArray(config('enterprise-billing-governance.seat_types'));
        $this->assertIsArray(config('enterprise-billing-governance.subscription_governance'));
        $this->assertIsArray(config('enterprise-billing-governance.financial_governance'));
        $this->assertSame('v1.1 Enterprise Reporting, Evidence Export & Dashboard Specification', config('enterprise-billing-governance.step75.recommended_next_phase'));
    }

    public function test_specification_service_reports_required_scores(): void
    {
        $report = app(EnterpriseBillingGovernanceSpecificationService::class)->report();

        $this->assertSame(46, $report['scores']['enterprise_billing_readiness']);
        $this->assertSame(42, $report['scores']['seat_governance_readiness']);
        $this->assertSame(48, $report['scores']['subscription_governance_readiness']);
        $this->assertSame(50, $report['scores']['ownership_governance_readiness']);
        $this->assertSame(41, $report['scores']['cost_governance_readiness']);
        $this->assertSame(39, $report['scores']['financial_governance_readiness']);
    }

    public function test_billing_ownership_seat_and_subscription_models_are_specified(): void
    {
        $report = app(EnterpriseBillingGovernanceSpecificationService::class)->report();

        $this->assertArrayHasKey('organization_billing', $report['billing_domains']);
        $this->assertArrayHasKey('enterprise_ownership', $report['ownership_governance']);
        $this->assertArrayHasKey('service_seat', $report['seat_types']);
        $this->assertContains('reclaimed', $report['seat_lifecycle']);
        $this->assertArrayHasKey('enterprise_contract', $report['subscription_governance']);
        $this->assertArrayHasKey('contract_manager', $report['billing_roles']);
    }

    public function test_cost_usage_contract_risk_audit_reporting_and_finance_models_are_specified(): void
    {
        $report = app(EnterpriseBillingGovernanceSpecificationService::class)->report();

        $this->assertArrayHasKey('seat_costs', $report['cost_governance']);
        $this->assertArrayHasKey('organization_usage', $report['usage_governance']);
        $this->assertContains('renewal_review', $report['contract_model']['contract_lifecycle']);
        $this->assertArrayHasKey('seat_leakage_risk', $report['billing_risks']);
        $this->assertContains('billing_policy_change', $report['billing_audit_events']);
        $this->assertContains('ownership_dashboard', $report['reporting_readiness']);
        $this->assertArrayHasKey('financial_audit_readiness', $report['financial_governance']);
    }

    public function test_enterprise_billing_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-enterprise-billing-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-seat-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-subscription-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-ownership-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-cost-governance-analysis.md'));
        $this->assertTrue(File::exists($base.'/v1.1-enterprise-contract-model.md'));
        $this->assertStringContainsString('Billing Domains', File::get($base.'/v1.1-enterprise-billing-specification.md'));
        $this->assertStringContainsString('Seat Lifecycle', File::get($base.'/v1.1-seat-governance-specification.md'));
        $this->assertStringContainsString('Subscription Models', File::get($base.'/v1.1-subscription-governance-specification.md'));
        $this->assertStringContainsString('Ownership Types', File::get($base.'/v1.1-ownership-governance-specification.md'));
        $this->assertStringContainsString('Cost Categories', File::get($base.'/v1.1-cost-governance-analysis.md'));
        $this->assertStringContainsString('Contract Lifecycle', File::get($base.'/v1.1-enterprise-contract-model.md'));
    }

    public function test_enterprise_billing_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:enterprise-billing-status')
            ->expectsOutput('v1.1 enterprise billing governance summary')
            ->expectsOutput('Enterprise Billing Readiness Score: 46')
            ->expectsOutput('Seat Governance Readiness Score: 42')
            ->expectsOutput('Subscription Governance Readiness Score: 48')
            ->expectsOutput('Ownership Governance Readiness Score: 50')
            ->expectsOutput('Cost Governance Readiness Score: 41')
            ->expectsOutput('Financial Governance Readiness Score: 39')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step74_does_not_add_enterprise_billing_seat_contract_or_organization_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'enterprise_billing')
                || str_contains($name, 'seat_governance')
                || str_contains($name, 'contract_governance')
                || str_contains($name, 'organization_billing'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_payment_or_secret_data(): void
    {
        $encoded = json_encode(app(EnterpriseBillingGovernanceSpecificationService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('plain_text_card', $encoded);
        $this->assertStringNotContainsString('provider_secret_value', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
