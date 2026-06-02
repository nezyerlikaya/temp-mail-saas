<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\OrganizationRoadmapPlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrganizationRoadmapPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_roadmap_config_is_readable_and_planning_only(): void
    {
        $this->assertIsArray(config('organization-roadmap.future_account_models'));
        $this->assertIsArray(config('organization-roadmap.tenancy_options'));
        $this->assertSame('recommended-starting-point', config('organization-roadmap.tenancy_options.shared_database.fit'));
        $this->assertSame('v1.1 Enterprise & Organization Domain Model Specification', config('organization-roadmap.step67.recommended_next_phase'));
    }

    public function test_organization_roadmap_service_reports_scores_and_current_layers(): void
    {
        $report = app(OrganizationRoadmapPlanningService::class)->report();

        $this->assertSame(62, $report['scores']['enterprise_readiness']);
        $this->assertSame(48, $report['scores']['organization_readiness']);
        $this->assertSame(66, $report['scores']['governance_readiness']);
        $this->assertSame(70, $report['scores']['security_readiness']);
        $this->assertSame(35, $report['scores']['multi_tenant_readiness']);
        $this->assertTrue($report['current_system']['user_layer']['users']);
        $this->assertTrue($report['current_system']['rbac_layer']['roles']);
        $this->assertTrue($report['current_system']['automation_layer']['automation_rules']);
    }

    public function test_future_models_and_billing_analysis_are_available(): void
    {
        $report = app(OrganizationRoadmapPlanningService::class)->report();

        $this->assertArrayHasKey('personal', $report['future_account_models']);
        $this->assertArrayHasKey('team', $report['future_account_models']);
        $this->assertArrayHasKey('business', $report['future_account_models']);
        $this->assertArrayHasKey('enterprise', $report['future_account_models']);
        $this->assertSame('highest', $report['billing_analysis']['hybrid_billing']['fit']);
    }

    public function test_tenancy_analysis_contains_required_options(): void
    {
        $report = app(OrganizationRoadmapPlanningService::class)->report();

        $this->assertArrayHasKey('single_database', $report['tenancy_analysis']);
        $this->assertArrayHasKey('shared_database', $report['tenancy_analysis']);
        $this->assertArrayHasKey('schema_per_tenant', $report['tenancy_analysis']);
        $this->assertArrayHasKey('database_per_tenant', $report['tenancy_analysis']);
    }

    public function test_planning_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-enterprise-organization-readiness.md'));
        $this->assertTrue(File::exists($base.'/v1.1-organization-roadmap.md'));
        $this->assertTrue(File::exists($base.'/v1.1-enterprise-gap-analysis.md'));
        $this->assertStringContainsString('Enterprise Readiness Score', File::get($base.'/v1.1-enterprise-organization-readiness.md'));
        $this->assertStringContainsString('STEP67', File::get($base.'/v1.1-organization-roadmap.md'));
        $this->assertStringContainsString('Tenant', File::get($base.'/v1.1-enterprise-gap-analysis.md'));
    }

    public function test_organization_roadmap_command_outputs_safe_summary_without_writing_data(): void
    {
        $before = OperationsEvent::query()->count();

        $this->artisan('system:organization-roadmap-status')
            ->expectsOutput('v1.1 organization roadmap summary')
            ->expectsOutput('Enterprise Readiness Score: 62')
            ->expectsOutput('Organization Readiness Score: 48')
            ->expectsOutput('Governance Readiness Score: 66')
            ->expectsOutput('Security Readiness Score: 70')
            ->expectsOutput('Multi-Tenant Readiness Score: 35')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
    }

    public function test_step66_does_not_add_new_workspace_or_tenant_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'workspace')
                || str_contains($name, 'tenant'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
        $this->assertTrue(File::exists(database_path('migrations/2026_05_31_000023_create_organizations_table.php')));
        $this->assertTrue(File::exists(database_path('migrations/2026_05_31_000024_create_organization_members_table.php')));
    }

    public function test_report_does_not_expose_personal_or_secret_data(): void
    {
        $encoded = json_encode(app(OrganizationRoadmapPlanningService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('password', $encoded);
        $this->assertStringNotContainsString('api_key', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
