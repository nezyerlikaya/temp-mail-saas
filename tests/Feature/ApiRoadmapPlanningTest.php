<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\ApiDocumentationReviewService;
use App\Services\Roadmap\ApiLifecycleReviewService;
use App\Services\Roadmap\ApiRoadmapPlanningService;
use App\Services\Roadmap\ApiUsabilityReviewService;
use App\Services\Roadmap\DeveloperExperiencePrioritizationService;
use App\Services\Roadmap\DeveloperOnboardingReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ApiRoadmapPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_usability_review_reports_excellent_state_and_event(): void
    {
        $report = app(ApiUsabilityReviewService::class)->review();

        $this->assertSame('excellent', $report['state']);
        $this->assertSame([], $report['blockers']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'api_review_completed']);
    }

    public function test_api_usability_review_can_report_acceptable_and_improvement_needed(): void
    {
        Config::set('api-roadmap.api_review.consistency_ready', false);
        $this->assertSame('acceptable', app(ApiUsabilityReviewService::class)->review()['state']);

        Config::set('api-roadmap.api_review.endpoint_discoverability_ready', false);
        $report = app(ApiUsabilityReviewService::class)->review();

        $this->assertSame('improvement-needed', $report['state']);
        $this->assertContains('endpoint_discoverability', array_column($report['blockers'], 'name'));
    }

    public function test_api_lifecycle_review_reports_lifecycle_checks_and_event(): void
    {
        $report = app(ApiLifecycleReviewService::class)->review();

        $this->assertSame('excellent', $report['state']);
        $this->assertContains('versioning_readiness', array_column($report['checks'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'api_lifecycle_review_completed']);
    }

    public function test_developer_onboarding_review_can_warn_and_records_event(): void
    {
        Config::set('api-roadmap.onboarding.authentication_understanding_ready', false);

        $report = app(DeveloperOnboardingReviewService::class)->review();

        $this->assertSame('acceptable', $report['state']);
        $this->assertContains('authentication_understanding', array_column($report['warnings'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'developer_experience_review_completed']);
    }

    public function test_api_documentation_review_reports_error_documentation_warning(): void
    {
        Config::set('api-roadmap.documentation.errors_ready', false);

        $report = app(ApiDocumentationReviewService::class)->review();

        $this->assertSame('acceptable', $report['state']);
        $this->assertContains('error_documentation', array_column($report['warnings'], 'name'));
    }

    public function test_dx_prioritization_groups_candidates(): void
    {
        $report = app(DeveloperExperiencePrioritizationService::class)->report();

        $this->assertGreaterThanOrEqual(5, $report['candidate_count']);
        $this->assertNotEmpty($report['quick_wins']);
        $this->assertNotEmpty($report['onboarding_improvements']);
        $this->assertNotEmpty($report['documentation_improvements']);
        $this->assertContains('api_authentication_examples', array_column($report['quick_wins'], 'key'));
    }

    public function test_api_roadmap_planning_aggregates_reviews_and_events(): void
    {
        $report = app(ApiRoadmapPlanningService::class)->report();

        $this->assertSame('excellent', $report['summary']['state']);
        $this->assertArrayHasKey('api', $report['reviews']);
        $this->assertArrayHasKey('lifecycle', $report['reviews']);
        $this->assertArrayHasKey('documentation', $report['reviews']);
        $this->assertNotEmpty($report['roadmap']['phase_1']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'api_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'api_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'api_lifecycle_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'developer_experience_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'api_roadmap_generated']);
    }

    public function test_api_roadmap_command_outputs_safe_summary(): void
    {
        $this->artisan('system:api-roadmap-status')
            ->expectsOutput('v1.1 API roadmap summary')
            ->expectsOutput('API usability: EXCELLENT')
            ->expectsOutput('API lifecycle: EXCELLENT')
            ->expectsOutput('Developer onboarding: EXCELLENT')
            ->expectsOutput('API documentation: EXCELLENT')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->doesntExpectOutputToContain('tm_private_api_key')
            ->assertSuccessful();
    }

    public function test_api_roadmap_reports_do_not_expose_keys_or_secrets(): void
    {
        Config::set('api-roadmap.roadmap.candidates', [
            [
                'key' => 'safe_api_candidate',
                'title' => 'Improve API docs for developer@example.com <b>secret</b>',
                'category' => 'documentation',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
        ]);

        $encoded = json_encode(app(ApiRoadmapPlanningService::class)->report(), JSON_THROW_ON_ERROR);
        $events = OperationsEvent::query()->where('source', 'api-roadmap')->get()->toJson();

        $this->assertStringNotContainsString('developer@example.com', $encoded);
        $this->assertStringNotContainsString('<b>', $encoded);
        $this->assertStringNotContainsString('developer@example.com', $events);
        $this->assertStringNotContainsString('secret', $events);
    }
}
