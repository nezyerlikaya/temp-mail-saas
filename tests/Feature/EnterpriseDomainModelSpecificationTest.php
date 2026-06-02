<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\EnterpriseDomainModelSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnterpriseDomainModelSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_domain_model_config_is_readable(): void
    {
        $this->assertIsArray(config('enterprise-domain-model.domains'));
        $this->assertIsArray(config('enterprise-domain-model.role_model'));
        $this->assertIsArray(config('enterprise-domain-model.tenant_boundaries'));
        $this->assertSame('v1.1 Enterprise Data Ownership & Access Policy Specification', config('enterprise-domain-model.step68.recommended_next_phase'));
    }

    public function test_specification_service_reports_required_scores(): void
    {
        $report = app(EnterpriseDomainModelSpecificationService::class)->report();

        $this->assertSame(58, $report['scores']['organization_domain_maturity']);
        $this->assertSame(45, $report['scores']['workspace_domain_maturity']);
        $this->assertSame(52, $report['scores']['membership_domain_maturity']);
        $this->assertSame(50, $report['scores']['governance_domain_maturity']);
        $this->assertSame(55, $report['scores']['enterprise_security_domain']);
        $this->assertSame(40, $report['scores']['tenant_boundary_readiness']);
    }

    public function test_domain_specification_contains_future_entities_without_implementing_models(): void
    {
        $report = app(EnterpriseDomainModelSpecificationService::class)->report();

        $this->assertSame('Organization', $report['domains']['organization']['future_entity']);
        $this->assertSame('Workspace', $report['domains']['workspace']['future_entity']);
        $this->assertSame('Team', $report['domains']['team']['future_entity']);
        $this->assertSame('Membership', $report['domains']['membership']['future_entity']);
        $this->assertSame('specification-only', $report['domains']['organization']['implementation_status']);
        $this->assertFalse(class_exists('App\\Models\\Workspace'));
        $this->assertFalse(class_exists('App\\Models\\Team'));
    }

    public function test_membership_seat_governance_and_security_domains_are_specified(): void
    {
        $report = app(EnterpriseDomainModelSpecificationService::class)->report();

        $this->assertContains('invited', $report['domains']['membership']['states']);
        $this->assertContains('assigned', $report['domains']['seat']['states']);
        $this->assertArrayHasKey('policy', $report['governance_domain']);
        $this->assertArrayHasKey('saml_sso', $report['security_domain']);
        $this->assertContains('mailboxes', $report['resource_ownership']);
        $this->assertContains('OrganizationCreated', $report['domain_events']);
        $this->assertContains('organization_api', $report['future_api_domains']);
    }

    public function test_specification_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-domain-model-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-membership-lifecycle-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-governance-domain-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-tenant-boundary-specification.md'));
        $this->assertStringContainsString('Organization Domain', File::get($base.'/v1.1-domain-model-specification.md'));
        $this->assertStringContainsString('Membership States', File::get($base.'/v1.1-membership-lifecycle-specification.md'));
        $this->assertStringContainsString('Governance Entities', File::get($base.'/v1.1-governance-domain-specification.md'));
        $this->assertStringContainsString('Boundary Types', File::get($base.'/v1.1-tenant-boundary-specification.md'));
    }

    public function test_enterprise_domain_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:enterprise-domain-status')
            ->expectsOutput('v1.1 enterprise domain model summary')
            ->expectsOutput('Organization Domain Maturity Score: 58')
            ->expectsOutput('Workspace Domain Maturity Score: 45')
            ->expectsOutput('Membership Domain Maturity Score: 52')
            ->expectsOutput('Governance Domain Maturity Score: 50')
            ->expectsOutput('Enterprise Security Domain Score: 55')
            ->expectsOutput('Tenant Boundary Readiness Score: 40')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step67_does_not_add_workspace_team_or_tenant_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'workspace')
                || str_contains($name, 'team')
                || str_contains($name, 'tenant')
                || str_contains($name, 'seat'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_or_secret_data(): void
    {
        $encoded = json_encode(app(EnterpriseDomainModelSpecificationService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('password', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
