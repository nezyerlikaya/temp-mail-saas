<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\EnterpriseGovernanceSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnterpriseGovernanceSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_governance_config_is_readable(): void
    {
        $this->assertIsArray(config('enterprise-governance.governance_domains'));
        $this->assertIsArray(config('enterprise-governance.compliance_readiness'));
        $this->assertIsArray(config('enterprise-governance.policies'));
        $this->assertIsArray(config('enterprise-governance.access_reviews'));
        $this->assertSame('v1.1 Enterprise Compliance Evidence & Control Mapping Specification', config('enterprise-governance.step70.recommended_next_phase'));
    }

    public function test_specification_service_reports_required_scores(): void
    {
        $report = app(EnterpriseGovernanceSpecificationService::class)->report();

        $this->assertSame(57, $report['scores']['governance_readiness']);
        $this->assertSame(49, $report['scores']['compliance_readiness']);
        $this->assertSame(51, $report['scores']['policy_management_readiness']);
        $this->assertSame(47, $report['scores']['access_review_readiness']);
        $this->assertSame(60, $report['scores']['audit_readiness']);
        $this->assertSame(53, $report['scores']['incident_governance_readiness']);
    }

    public function test_governance_policy_access_review_and_risk_domains_are_specified(): void
    {
        $report = app(EnterpriseGovernanceSpecificationService::class)->report();

        $this->assertArrayHasKey('identity_governance', $report['governance_domains']);
        $this->assertArrayHasKey('export_policy', $report['policies']);
        $this->assertContains('organization_access_review', $report['access_reviews']['types']);
        $this->assertContains('archived', $report['access_reviews']['lifecycle']);
        $this->assertArrayHasKey('compliance_risk', $report['risk_categories']);
    }

    public function test_audit_incident_retention_and_dashboard_planning_are_specified(): void
    {
        $report = app(EnterpriseGovernanceSpecificationService::class)->report();

        $this->assertContains('data_exports', $report['audit_events']);
        $this->assertContains('correlation_id', $report['audit_fields']);
        $this->assertContains('provider_incident', $report['incident_governance']['types']);
        $this->assertContains('reported', $report['incident_governance']['lifecycle']);
        $this->assertArrayHasKey('ephemeral_mail_data', $report['retention_governance']);
        $this->assertContains('risk_heatmap', $report['dashboard_planning']);
    }

    public function test_governance_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-enterprise-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-compliance-readiness-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-policy-management-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-access-review-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-risk-audit-incident-governance.md'));
        $this->assertStringContainsString('Governance Domains', File::get($base.'/v1.1-enterprise-governance-specification.md'));
        $this->assertStringContainsString('SOC 2 Readiness', File::get($base.'/v1.1-compliance-readiness-specification.md'));
        $this->assertStringContainsString('Policy Types', File::get($base.'/v1.1-policy-management-specification.md'));
        $this->assertStringContainsString('Review Types', File::get($base.'/v1.1-access-review-specification.md'));
        $this->assertStringContainsString('Incident Governance', File::get($base.'/v1.1-risk-audit-incident-governance.md'));
    }

    public function test_enterprise_governance_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:enterprise-governance-status')
            ->expectsOutput('v1.1 enterprise governance summary')
            ->expectsOutput('Governance Readiness Score: 57')
            ->expectsOutput('Compliance Readiness Score: 49')
            ->expectsOutput('Policy Management Readiness Score: 51')
            ->expectsOutput('Access Review Readiness Score: 47')
            ->expectsOutput('Audit Readiness Score: 60')
            ->expectsOutput('Incident Governance Readiness Score: 53')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step69_does_not_add_policy_compliance_access_review_or_tenant_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'policy')
                || str_contains($name, 'compliance')
                || str_contains($name, 'access_review')
                || str_contains($name, 'tenant'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_or_secret_data(): void
    {
        $encoded = json_encode(app(EnterpriseGovernanceSpecificationService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('password', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
