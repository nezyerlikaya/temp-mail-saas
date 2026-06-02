<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\AutomationCapabilityReviewService;
use App\Services\Roadmap\AutomationEnhancementPrioritizationService;
use App\Services\Roadmap\AutomationLifecycleReviewService;
use App\Services\Roadmap\AutomationRoadmapPlanningService;
use App\Services\Roadmap\IntelligenceCapabilityReviewService;
use App\Services\Roadmap\OperationalIntelligenceReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AutomationRoadmapPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_automation_capability_review_reports_excellent_state_and_event(): void
    {
        $report = app(AutomationCapabilityReviewService::class)->review();

        $this->assertSame('excellent', $report['state']);
        $this->assertSame([], $report['blockers']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'automation_review_completed']);
    }

    public function test_automation_capability_review_can_report_acceptable_and_improvement_needed(): void
    {
        Config::set('automation-roadmap.automation_review.maintainability_ready', false);
        $this->assertSame('acceptable', app(AutomationCapabilityReviewService::class)->review()['state']);

        Config::set('automation-roadmap.automation_review.rule_coverage_ready', false);
        $report = app(AutomationCapabilityReviewService::class)->review();

        $this->assertSame('improvement-needed', $report['state']);
        $this->assertContains('automation_rule_coverage', array_column($report['blockers'], 'name'));
    }

    public function test_intelligence_capability_review_reports_checks_and_event(): void
    {
        $report = app(IntelligenceCapabilityReviewService::class)->review();

        $this->assertSame('excellent', $report['state']);
        $this->assertContains('scoring_systems', array_column($report['checks'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'intelligence_review_completed']);
    }

    public function test_automation_lifecycle_review_can_warn(): void
    {
        Config::set('automation-roadmap.lifecycle_review.audit_ready', false);

        $report = app(AutomationLifecycleReviewService::class)->review();

        $this->assertSame('acceptable', $report['state']);
        $this->assertContains('audit_lifecycle', array_column($report['warnings'], 'name'));
    }

    public function test_operational_intelligence_review_can_warn(): void
    {
        Config::set('automation-roadmap.operational_intelligence.provider_ready', false);

        $report = app(OperationalIntelligenceReviewService::class)->review();

        $this->assertSame('acceptable', $report['state']);
        $this->assertContains('provider_intelligence', array_column($report['warnings'], 'name'));
    }

    public function test_automation_prioritization_groups_candidates_and_records_event(): void
    {
        $report = app(AutomationEnhancementPrioritizationService::class)->report();

        $this->assertGreaterThanOrEqual(5, $report['candidate_count']);
        $this->assertNotEmpty($report['quick_wins']);
        $this->assertNotEmpty($report['high_impact_enhancements']);
        $this->assertNotEmpty($report['low_risk_improvements']);
        $this->assertContains('automation_audit_guidance', array_column($report['quick_wins'], 'key'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'automation_priorities_generated']);
    }

    public function test_automation_roadmap_planning_aggregates_reviews_and_events(): void
    {
        $report = app(AutomationRoadmapPlanningService::class)->report();

        $this->assertSame('excellent', $report['summary']['state']);
        $this->assertArrayHasKey('automation', $report['reviews']);
        $this->assertArrayHasKey('intelligence', $report['reviews']);
        $this->assertArrayHasKey('operations', $report['reviews']);
        $this->assertNotEmpty($report['roadmap']['phase_1']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'automation_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'automation_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'intelligence_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'automation_priorities_generated']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'automation_roadmap_generated']);
    }

    public function test_automation_roadmap_command_outputs_safe_summary(): void
    {
        $this->artisan('system:automation-roadmap-status')
            ->expectsOutput('v1.1 automation roadmap summary')
            ->expectsOutput('Automation capability: EXCELLENT')
            ->expectsOutput('Intelligence capability: EXCELLENT')
            ->expectsOutput('Automation lifecycle: EXCELLENT')
            ->expectsOutput('Operational intelligence: EXCELLENT')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->doesntExpectOutputToContain('private-rule-payload')
            ->assertSuccessful();
    }

    public function test_automation_roadmap_reports_do_not_expose_rule_payloads_or_secrets(): void
    {
        Config::set('automation-roadmap.roadmap.candidates', [
            [
                'key' => 'safe_automation_candidate',
                'title' => 'Improve rule planning for operator@example.com <b>secret</b>',
                'category' => 'automation',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
        ]);

        $encoded = json_encode(app(AutomationRoadmapPlanningService::class)->report(), JSON_THROW_ON_ERROR);
        $events = OperationsEvent::query()->where('source', 'automation-roadmap')->get()->toJson();

        $this->assertStringNotContainsString('operator@example.com', $encoded);
        $this->assertStringNotContainsString('<b>', $encoded);
        $this->assertStringNotContainsString('operator@example.com', $events);
        $this->assertStringNotContainsString('secret', $events);
    }
}
