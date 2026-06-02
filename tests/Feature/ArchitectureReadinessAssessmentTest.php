<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\ArchitectureReadinessAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ArchitectureReadinessAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_architecture_readiness_config_is_readable(): void
    {
        $this->assertIsArray(config('architecture-readiness.readiness_scores'));
        $this->assertIsArray(config('architecture-readiness.risk_scores'));
        $this->assertIsArray(config('architecture-readiness.roadmap_closure'));
        $this->assertSame('STEP01-STEP75', config('architecture-readiness.roadmap_closure.completed'));
    }

    public function test_assessment_service_reports_required_readiness_scores(): void
    {
        $report = app(ArchitectureReadinessAssessmentService::class)->report();

        $this->assertSame(91, $report['scores']['core_product_readiness']);
        $this->assertSame(88, $report['scores']['production_readiness']);
        $this->assertSame(86, $report['scores']['security_readiness']);
        $this->assertSame(76, $report['scores']['api_readiness']);
        $this->assertSame(70, $report['scores']['automation_readiness']);
        $this->assertSame(52, $report['scores']['enterprise_readiness']);
        $this->assertSame(60, $report['scores']['governance_readiness']);
        $this->assertSame(55, $report['scores']['identity_readiness']);
        $this->assertSame(53, $report['scores']['authorization_readiness']);
        $this->assertSame(46, $report['scores']['billing_readiness']);
        $this->assertSame(34, $report['scores']['multi_tenant_readiness']);
        $this->assertSame(70, $report['scores']['overall_architecture_readiness']);
    }

    public function test_complete_architecture_review_and_risk_scores_are_specified(): void
    {
        $report = app(ArchitectureReadinessAssessmentService::class)->report();

        $this->assertContains('authentication', $report['architecture_layers']['core_saas']);
        $this->assertContains('public_inbox', $report['architecture_layers']['temp_mail']);
        $this->assertContains('identity_governance', $report['architecture_layers']['security']);
        $this->assertContains('billing_models', $report['architecture_layers']['enterprise']);
        $this->assertSame(24, $report['risk_scores']['security_risk']);
        $this->assertSame(55, $report['risk_scores']['enterprise_risk']);
    }

    public function test_gap_debt_recommendation_and_roadmap_closure_models_are_specified(): void
    {
        $report = app(ArchitectureReadinessAssessmentService::class)->report();

        $this->assertNotEmpty($report['strongest_areas']);
        $this->assertNotEmpty($report['weakest_areas']);
        $this->assertCount(5, $report['critical_gaps']);
        $this->assertNotEmpty($report['technical_debt']['high_priority']);
        $this->assertArrayHasKey('security', $report['recommendations']);
        $this->assertContains('Enterprise Edition', $report['future_opportunities']);
        $this->assertSame('Closed', $report['roadmap_closure']['roadmap']);
        $this->assertSame('Future work requires a new roadmap.', $report['roadmap_closure']['future_work_rule']);
    }

    public function test_architecture_final_assessment_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-architecture-final-assessment.md'));
        $this->assertTrue(File::exists($base.'/v1.1-readiness-scorecard.md'));
        $this->assertTrue(File::exists($base.'/v1.1-gap-analysis-final.md'));
        $this->assertTrue(File::exists($base.'/v1.1-technical-debt-review.md'));
        $this->assertTrue(File::exists($base.'/v1.1-roadmap-closure-report.md'));
        $this->assertTrue(File::exists($base.'/v1.1-future-opportunities-report.md'));
        $this->assertStringContainsString('Complete Architecture Review', File::get($base.'/v1.1-architecture-final-assessment.md'));
        $this->assertStringContainsString('Overall Architecture Readiness', File::get($base.'/v1.1-readiness-scorecard.md'));
        $this->assertStringContainsString('Critical Gaps', File::get($base.'/v1.1-gap-analysis-final.md'));
        $this->assertStringContainsString('Architectural Risks', File::get($base.'/v1.1-technical-debt-review.md'));
        $this->assertStringContainsString('Future Work Rule', File::get($base.'/v1.1-roadmap-closure-report.md'));
        $this->assertStringContainsString('Optional Edition Tracks', File::get($base.'/v1.1-future-opportunities-report.md'));
    }

    public function test_architecture_readiness_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:architecture-readiness-status')
            ->expectsOutput('v1.1 architecture readiness final assessment')
            ->expectsOutput('Core Product Readiness Score: 91')
            ->expectsOutput('Production Readiness Score: 88')
            ->expectsOutput('Security Readiness Score: 86')
            ->expectsOutput('API Readiness Score: 76')
            ->expectsOutput('Automation Readiness Score: 70')
            ->expectsOutput('Enterprise Readiness Score: 52')
            ->expectsOutput('Governance Readiness Score: 60')
            ->expectsOutput('Identity Readiness Score: 55')
            ->expectsOutput('Authorization Readiness Score: 53')
            ->expectsOutput('Billing Readiness Score: 46')
            ->expectsOutput('Multi-Tenant Readiness Score: 34')
            ->expectsOutput('Overall Architecture Readiness Score: 70')
            ->expectsOutput('STEP01-STEP75 completed')
            ->expectsOutput('Temp Mail SaaS v1.1 Architecture Complete')
            ->expectsOutput('Roadmap Closed')
            ->expectsOutput('Future work requires a new roadmap.')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step75_does_not_add_assessment_tenant_enterprise_or_organization_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'architecture_readiness')
                || str_contains($name, 'tenant_edition')
                || str_contains($name, 'enterprise_edition')
                || str_contains($name, 'organization_edition'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_payment_or_secret_data(): void
    {
        $encoded = json_encode(app(ArchitectureReadinessAssessmentService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('plain_text_card', $encoded);
        $this->assertStringNotContainsString('provider_secret_value', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
