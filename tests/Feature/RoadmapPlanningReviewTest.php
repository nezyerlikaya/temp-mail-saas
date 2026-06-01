<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\ArchitectureReviewService;
use App\Services\Roadmap\MaintainabilityReviewService;
use App\Services\Roadmap\ReleasePrioritizationService;
use App\Services\Roadmap\ScalabilityReviewService;
use App\Services\Roadmap\TechnicalDebtReviewService;
use App\Services\Roadmap\V11RoadmapPlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoadmapPlanningReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_debt_review_classifies_inventory(): void
    {
        $report = app(TechnicalDebtReviewService::class)->review();

        $this->assertSame('ready', $report['status']);
        $this->assertNotEmpty($report['items']);
        $this->assertArrayHasKey('severity', $report['items'][0]);
        $this->assertArrayHasKey('priority', $report['items'][0]);
        $this->assertArrayHasKey('risk', $report['items'][0]);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'technical_debt_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'technical_debt_review_completed']);
    }

    public function test_technical_debt_review_blocks_critical_items(): void
    {
        config(['roadmap.debt_review.areas' => [[
            'key' => 'critical_example',
            'severity' => 'critical',
            'priority' => 'v1.1',
            'risk' => 'critical',
        ]]]);

        $this->assertSame('blocked', app(TechnicalDebtReviewService::class)->review()['status']);
    }

    public function test_architecture_review_can_warn(): void
    {
        config(['roadmap.architecture.dependency_structure_reviewed' => false]);

        $report = app(ArchitectureReviewService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('dependency_structure', array_column($report['warnings'], 'name'));
    }

    public function test_scalability_review_can_warn(): void
    {
        config(['roadmap.scalability.queue_reviewed' => false]);

        $report = app(ScalabilityReviewService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('queue_scalability', array_column($report['warnings'], 'name'));
    }

    public function test_maintainability_review_can_warn(): void
    {
        config(['roadmap.maintainability.documentation_coverage_reviewed' => false]);

        $report = app(MaintainabilityReviewService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('documentation_coverage', array_column($report['warnings'], 'name'));
    }

    public function test_release_prioritization_groups_future_candidates(): void
    {
        $report = app(ReleasePrioritizationService::class)->summarize();

        $this->assertSame(3, $report['counts']['v1.1']);
        $this->assertSame(2, $report['counts']['v1.2']);
        $this->assertSame(2, $report['counts']['future']);
    }

    public function test_roadmap_planning_service_aggregates_reviews_and_events(): void
    {
        $report = app(V11RoadmapPlanningService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertNotEmpty($report['opportunities']);
        $this->assertNotEmpty($report['risks']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'roadmap_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'roadmap_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'roadmap_prioritized']);
    }

    public function test_roadmap_command_outputs_safe_summary(): void
    {
        $this->artisan('system:roadmap-status')
            ->expectsOutput('Roadmap readiness: READY')
            ->expectsOutput('Technical debt items: 3')
            ->expectsOutput('v1.1 candidates: 3')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();
    }

    public function test_roadmap_command_fails_on_critical_debt_without_sensitive_output(): void
    {
        config(['roadmap.debt_review.areas' => [[
            'key' => 'critical_maintenance_gap',
            'summary' => 'Resolve maintenance gap.',
            'severity' => 'critical',
            'priority' => 'v1.1',
            'risk' => 'critical',
        ]]]);

        $this->artisan('system:roadmap-status')
            ->expectsOutput('Roadmap readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: critical_maintenance_gap')
            ->doesntExpectOutputToContain('password')
            ->assertFailed();
    }

    public function test_roadmap_report_and_events_do_not_expose_sensitive_data(): void
    {
        $encoded = json_encode(app(V11RoadmapPlanningService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertTrue(OperationsEvent::query()->where('event_type', 'roadmap_prioritized')->exists());
        $this->assertStringNotContainsString('test@example.com', $encoded);
        $this->assertStringNotContainsString('mailbox@example.test', $encoded);
        $this->assertStringNotContainsString('raw-email-body', $encoded);
        $this->assertStringNotContainsString('provider-secret', $encoded);
    }
}
