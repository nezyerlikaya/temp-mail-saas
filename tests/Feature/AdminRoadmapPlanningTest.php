<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\AdminAccessibilityReviewService;
use App\Services\Roadmap\AdminRoadmapPlanningService;
use App\Services\Roadmap\AdminUXPrioritizationService;
use App\Services\Roadmap\AdminWorkflowReviewService;
use App\Services\Roadmap\DashboardUsabilityReviewService;
use App\Services\Roadmap\OperationsWorkflowReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminRoadmapPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_workflow_review_reports_excellent_state_and_event(): void
    {
        $report = app(AdminWorkflowReviewService::class)->review();

        $this->assertSame('excellent', $report['state']);
        $this->assertSame([], $report['blockers']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'admin_review_completed']);
    }

    public function test_admin_workflow_review_can_report_acceptable_and_improvement_needed(): void
    {
        Config::set('admin-roadmap.admin_workflow.navigation_ready', false);
        $this->assertSame('acceptable', app(AdminWorkflowReviewService::class)->review()['state']);

        Config::set('admin-roadmap.admin_workflow.daily_tasks_ready', false);
        $report = app(AdminWorkflowReviewService::class)->review();

        $this->assertSame('improvement-needed', $report['state']);
        $this->assertContains('daily_admin_tasks', array_column($report['blockers'], 'name'));
    }

    public function test_operations_workflow_review_reports_workflow_checks_and_event(): void
    {
        $report = app(OperationsWorkflowReviewService::class)->review();

        $this->assertSame('excellent', $report['state']);
        $this->assertContains('monitoring_workflow', array_column($report['checks'], 'name'));
        $this->assertContains('billing_workflow', array_column($report['checks'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'operations_workflow_review_completed']);
    }

    public function test_dashboard_usability_review_reports_quick_action_warning(): void
    {
        Config::set('admin-roadmap.dashboard_usability.quick_action_ready', false);

        $report = app(DashboardUsabilityReviewService::class)->review();

        $this->assertSame('acceptable', $report['state']);
        $this->assertContains('quick_action_readiness', array_column($report['warnings'], 'name'));
    }

    public function test_admin_accessibility_review_can_warn_and_records_event(): void
    {
        Config::set('admin-roadmap.accessibility.focus_management_ready', false);

        $report = app(AdminAccessibilityReviewService::class)->review();

        $this->assertSame('acceptable', $report['state']);
        $this->assertContains('focus_management_review', array_column($report['warnings'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'admin_accessibility_review_completed']);
    }

    public function test_admin_ux_prioritization_groups_candidates(): void
    {
        $report = app(AdminUXPrioritizationService::class)->report();

        $this->assertGreaterThanOrEqual(5, $report['candidate_count']);
        $this->assertNotEmpty($report['quick_wins']);
        $this->assertNotEmpty($report['operational_bottlenecks']);
        $this->assertNotEmpty($report['high_impact_improvements']);
        $this->assertContains('admin_accessibility_pass', array_column($report['quick_wins'], 'key'));
    }

    public function test_admin_roadmap_planning_aggregates_reviews_and_events(): void
    {
        $report = app(AdminRoadmapPlanningService::class)->report();

        $this->assertSame('excellent', $report['summary']['state']);
        $this->assertArrayHasKey('admin', $report['reviews']);
        $this->assertArrayHasKey('operations', $report['reviews']);
        $this->assertArrayHasKey('dashboard', $report['reviews']);
        $this->assertNotEmpty($report['roadmap']['phase_1']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'admin_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'admin_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'operations_workflow_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'admin_accessibility_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'admin_roadmap_generated']);
    }

    public function test_admin_roadmap_command_outputs_safe_summary(): void
    {
        $this->artisan('system:admin-roadmap-status')
            ->expectsOutput('v1.1 admin roadmap summary')
            ->expectsOutput('Admin workflow: EXCELLENT')
            ->expectsOutput('Operations workflow: EXCELLENT')
            ->expectsOutput('Dashboard usability: EXCELLENT')
            ->expectsOutput('Admin accessibility: EXCELLENT')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->doesntExpectOutputToContain('staff@example.com')
            ->assertSuccessful();
    }

    public function test_admin_roadmap_reports_do_not_expose_staff_personal_information(): void
    {
        Config::set('admin-roadmap.roadmap.candidates', [
            [
                'key' => 'safe_admin_candidate',
                'title' => 'Improve admin work for staff@example.com <b>secret</b>',
                'category' => 'admin-workflow',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
        ]);

        $encoded = json_encode(app(AdminRoadmapPlanningService::class)->report(), JSON_THROW_ON_ERROR);
        $events = OperationsEvent::query()->where('source', 'admin-roadmap')->get()->toJson();

        $this->assertStringNotContainsString('staff@example.com', $encoded);
        $this->assertStringNotContainsString('<b>', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $events);
        $this->assertStringNotContainsString('secret', $events);
    }
}
