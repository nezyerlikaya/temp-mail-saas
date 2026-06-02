<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\EnterpriseAuthorizationGovernanceSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnterpriseAuthorizationGovernanceSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_authorization_config_is_readable(): void
    {
        $this->assertIsArray(config('enterprise-authorization.authorization_domains'));
        $this->assertIsArray(config('enterprise-authorization.permission_types'));
        $this->assertIsArray(config('enterprise-authorization.access_boundaries'));
        $this->assertIsArray(config('enterprise-authorization.authorization_risks'));
        $this->assertSame('v1.1 Enterprise Policy Enforcement & Access Certification Specification', config('enterprise-authorization.step73.recommended_next_phase'));
    }

    public function test_specification_service_reports_required_scores(): void
    {
        $report = app(EnterpriseAuthorizationGovernanceSpecificationService::class)->report();

        $this->assertSame(53, $report['scores']['authorization_governance_readiness']);
        $this->assertSame(49, $report['scores']['permission_governance_readiness']);
        $this->assertSame(50, $report['scores']['delegated_administration_readiness']);
        $this->assertSame(46, $report['scores']['privileged_access_governance']);
        $this->assertSame(48, $report['scores']['access_review_readiness']);
        $this->assertSame(44, $report['scores']['separation_of_duties_readiness']);
    }

    public function test_authorization_permission_boundaries_and_delegation_are_specified(): void
    {
        $report = app(EnterpriseAuthorizationGovernanceSpecificationService::class)->report();

        $this->assertArrayHasKey('delegated_authorization', $report['authorization_domains']);
        $this->assertArrayHasKey('temporary_permission', $report['permission_types']);
        $this->assertContains('expiring', $report['permission_lifecycle']);
        $this->assertArrayHasKey('audit_boundary', $report['access_boundaries']);
        $this->assertArrayHasKey('support_operator', $report['delegated_roles']);
    }

    public function test_privileged_certification_sod_risk_reviews_and_service_authorization_are_specified(): void
    {
        $report = app(EnterpriseAuthorizationGovernanceSpecificationService::class)->report();

        $this->assertArrayHasKey('break_glass_access', $report['privileged_access']);
        $this->assertArrayHasKey('high_risk_review', $report['certification']);
        $this->assertArrayHasKey('auditor_vs_administrator', $report['separation_of_duties']);
        $this->assertArrayHasKey('toxic_combination_risk', $report['authorization_risks']);
        $this->assertContains('delegated_access_review', $report['access_reviews']['types']);
        $this->assertArrayHasKey('enterprise_connectors', $report['service_authorization']);
        $this->assertContains('access_certification', $report['audit_events']);
    }

    public function test_authorization_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-authorization-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-permission-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-access-boundary-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-delegated-administration-model.md'));
        $this->assertTrue(File::exists($base.'/v1.1-privileged-access-governance.md'));
        $this->assertTrue(File::exists($base.'/v1.1-separation-of-duties-analysis.md'));
        $this->assertStringContainsString('Authorization Domains', File::get($base.'/v1.1-authorization-governance-specification.md'));
        $this->assertStringContainsString('Permission Lifecycle', File::get($base.'/v1.1-permission-governance-specification.md'));
        $this->assertStringContainsString('Audit Boundary', File::get($base.'/v1.1-access-boundary-specification.md'));
        $this->assertStringContainsString('Future Roles', File::get($base.'/v1.1-delegated-administration-model.md'));
        $this->assertStringContainsString('Break Glass Access', File::get($base.'/v1.1-privileged-access-governance.md'));
        $this->assertStringContainsString('Approver vs Requester', File::get($base.'/v1.1-separation-of-duties-analysis.md'));
    }

    public function test_enterprise_authorization_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:enterprise-authorization-status')
            ->expectsOutput('v1.1 enterprise authorization governance summary')
            ->expectsOutput('Authorization Governance Readiness Score: 53')
            ->expectsOutput('Permission Governance Readiness Score: 49')
            ->expectsOutput('Delegated Administration Readiness Score: 50')
            ->expectsOutput('Privileged Access Governance Score: 46')
            ->expectsOutput('Access Review Readiness Score: 48')
            ->expectsOutput('Separation of Duties Readiness Score: 44')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step72_does_not_add_permission_delegation_access_review_or_tenant_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'permission_governance')
                || str_contains($name, 'delegation')
                || str_contains($name, 'access_review')
                || str_contains($name, 'tenant'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_or_secret_data(): void
    {
        $encoded = json_encode(app(EnterpriseAuthorizationGovernanceSpecificationService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('plain_text_password', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
