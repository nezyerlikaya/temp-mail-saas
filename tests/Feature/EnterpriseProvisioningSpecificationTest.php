<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\EnterpriseProvisioningSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnterpriseProvisioningSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_provisioning_config_is_readable(): void
    {
        $this->assertIsArray(config('enterprise-provisioning.provisioning_domains'));
        $this->assertIsArray(config('enterprise-provisioning.joiner_mover_leaver'));
        $this->assertIsArray(config('enterprise-provisioning.scim_readiness'));
        $this->assertIsArray(config('enterprise-provisioning.provisioning_risks'));
        $this->assertSame('v1.1 Enterprise Audit Evidence & Reporting Specification', config('enterprise-provisioning.step72.recommended_next_phase'));
    }

    public function test_specification_service_reports_required_scores(): void
    {
        $report = app(EnterpriseProvisioningSpecificationService::class)->report();

        $this->assertSame(45, $report['scores']['provisioning_readiness']);
        $this->assertSame(50, $report['scores']['lifecycle_governance_readiness']);
        $this->assertSame(48, $report['scores']['joiner_mover_leaver_readiness']);
        $this->assertSame(52, $report['scores']['organization_onboarding_readiness']);
        $this->assertSame(38, $report['scores']['scim_readiness']);
        $this->assertSame(55, $report['scores']['lifecycle_audit_readiness']);
    }

    public function test_provisioning_joiner_mover_leaver_and_access_lifecycle_are_specified(): void
    {
        $report = app(EnterpriseProvisioningSpecificationService::class)->report();

        $this->assertArrayHasKey('identity_provisioning', $report['provisioning_domains']);
        $this->assertContains('onboarded', $report['joiner_mover_leaver']['joiner']);
        $this->assertContains('validated', $report['joiner_mover_leaver']['mover']);
        $this->assertContains('revoked', $report['joiner_mover_leaver']['leaver']);
        $this->assertContains('emergency_access', $report['access_lifecycle']);
    }

    public function test_onboarding_offboarding_workspace_team_scim_and_risk_models_are_specified(): void
    {
        $report = app(EnterpriseProvisioningSpecificationService::class)->report();

        $this->assertArrayHasKey('enterprise_contract_organization', $report['organization_onboarding']);
        $this->assertArrayHasKey('security_suspension', $report['organization_offboarding']);
        $this->assertContains('deleted', $report['workspace_lifecycle']);
        $this->assertContains('merged', $report['team_lifecycle']);
        $this->assertArrayHasKey('membership_sync', $report['scim_readiness']);
        $this->assertArrayHasKey('orphaned_identity_risk', $report['provisioning_risks']);
        $this->assertContains('organization_closure', $report['lifecycle_audit_events']);
    }

    public function test_provisioning_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-provisioning-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-lifecycle-control-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-joiner-mover-leaver-model.md'));
        $this->assertTrue(File::exists($base.'/v1.1-organization-onboarding-offboarding.md'));
        $this->assertTrue(File::exists($base.'/v1.1-scim-readiness-analysis.md'));
        $this->assertStringContainsString('Provisioning Domains', File::get($base.'/v1.1-provisioning-specification.md'));
        $this->assertStringContainsString('Access Lifecycle Governance', File::get($base.'/v1.1-lifecycle-control-specification.md'));
        $this->assertStringContainsString('Joiner', File::get($base.'/v1.1-joiner-mover-leaver-model.md'));
        $this->assertStringContainsString('Offboarding Models', File::get($base.'/v1.1-organization-onboarding-offboarding.md'));
        $this->assertStringContainsString('SCIM User Lifecycle', File::get($base.'/v1.1-scim-readiness-analysis.md'));
    }

    public function test_enterprise_provisioning_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:enterprise-provisioning-status')
            ->expectsOutput('v1.1 enterprise provisioning summary')
            ->expectsOutput('Provisioning Readiness Score: 45')
            ->expectsOutput('Lifecycle Governance Readiness Score: 50')
            ->expectsOutput('Joiner/Mover/Leaver Readiness Score: 48')
            ->expectsOutput('Organization Onboarding Readiness Score: 52')
            ->expectsOutput('SCIM Readiness Score: 38')
            ->expectsOutput('Lifecycle Audit Readiness Score: 55')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step71_does_not_add_scim_provisioning_lifecycle_or_tenant_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'scim')
                || str_contains($name, 'provision')
                || str_contains($name, 'lifecycle')
                || str_contains($name, 'tenant'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_or_secret_data(): void
    {
        $encoded = json_encode(app(EnterpriseProvisioningSpecificationService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('plain_text_password', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
