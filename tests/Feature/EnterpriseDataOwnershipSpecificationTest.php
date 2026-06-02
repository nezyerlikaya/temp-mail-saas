<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\EnterpriseDataOwnershipSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnterpriseDataOwnershipSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_data_policy_config_is_readable(): void
    {
        $this->assertIsArray(config('enterprise-data-policy.ownership_domains'));
        $this->assertIsArray(config('enterprise-data-policy.resource_ownership_matrix'));
        $this->assertIsArray(config('enterprise-data-policy.access_policies'));
        $this->assertIsArray(config('enterprise-data-policy.audit_access'));
    }

    public function test_specification_service_reports_required_scores(): void
    {
        $report = app(EnterpriseDataOwnershipSpecificationService::class)->report();

        $this->assertSame(54, $report['scores']['data_ownership_readiness']);
        $this->assertSame(50, $report['scores']['access_policy_readiness']);
        $this->assertSame(52, $report['scores']['resource_ownership_maturity']);
        $this->assertSame(56, $report['scores']['governance_readiness']);
        $this->assertSame(46, $report['scores']['compliance_readiness']);
        $this->assertSame(58, $report['scores']['enterprise_audit_readiness']);
    }

    public function test_resource_ownership_matrix_and_access_policies_are_specified(): void
    {
        $report = app(EnterpriseDataOwnershipSpecificationService::class)->report();

        $this->assertArrayHasKey('mailboxes', $report['resource_ownership_matrix']);
        $this->assertArrayHasKey('domains', $report['resource_ownership_matrix']);
        $this->assertArrayHasKey('api_keys', $report['resource_ownership_matrix']);
        $this->assertArrayHasKey('audit_logs', $report['resource_ownership_matrix']);
        $this->assertArrayHasKey('direct_access', $report['access_policies']);
        $this->assertArrayHasKey('delegated_access', $report['access_policies']);
        $this->assertArrayHasKey('emergency_access', $report['access_policies']);
    }

    public function test_governance_compliance_isolation_and_api_ownership_are_specified(): void
    {
        $report = app(EnterpriseDataOwnershipSpecificationService::class)->report();

        $this->assertArrayHasKey('retention', $report['governance']);
        $this->assertArrayHasKey('gdpr', $report['compliance']);
        $this->assertArrayHasKey('logical_isolation', $report['resource_isolation']);
        $this->assertArrayHasKey('temporary_admin', $report['delegated_access_roles']);
        $this->assertArrayHasKey('organization_api_key', $report['api_ownership']);
        $this->assertContains('owner', $report['audit_access']['purge']);
    }

    public function test_policy_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-data-ownership-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-access-policy-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-resource-ownership-matrix.md'));
        $this->assertTrue(File::exists($base.'/v1.1-data-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-enterprise-audit-policy.md'));
        $this->assertStringContainsString('Personal Ownership', File::get($base.'/v1.1-data-ownership-specification.md'));
        $this->assertStringContainsString('Direct Access', File::get($base.'/v1.1-access-policy-specification.md'));
        $this->assertStringContainsString('Mailboxes', File::get($base.'/v1.1-resource-ownership-matrix.md'));
        $this->assertStringContainsString('Retention Governance', File::get($base.'/v1.1-data-governance-specification.md'));
        $this->assertStringContainsString('Who Can Export', File::get($base.'/v1.1-enterprise-audit-policy.md'));
    }

    public function test_enterprise_data_policy_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:enterprise-data-policy-status')
            ->expectsOutput('v1.1 enterprise data policy summary')
            ->expectsOutput('Data Ownership Readiness Score: 54')
            ->expectsOutput('Access Policy Readiness Score: 50')
            ->expectsOutput('Resource Ownership Maturity Score: 52')
            ->expectsOutput('Governance Readiness Score: 56')
            ->expectsOutput('Compliance Readiness Score: 46')
            ->expectsOutput('Enterprise Audit Readiness Score: 58')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step68_does_not_add_policy_tenant_workspace_or_team_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'data_policy')
                || str_contains($name, 'access_policy')
                || str_contains($name, 'workspace')
                || str_contains($name, 'team')
                || str_contains($name, 'tenant'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_or_secret_data(): void
    {
        $encoded = json_encode(app(EnterpriseDataOwnershipSpecificationService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('password', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
