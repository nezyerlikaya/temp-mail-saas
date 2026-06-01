<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\InboxAccessibilityReviewService;
use App\Services\Roadmap\InboxExperienceReviewService;
use App\Services\Roadmap\InboxRoadmapPlanningService;
use App\Services\Roadmap\InboxUXPrioritizationService;
use App\Services\Roadmap\MailboxLifecycleReviewService;
use App\Services\Roadmap\MessageWorkflowReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InboxRoadmapPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_experience_review_reports_excellent_state_and_event(): void
    {
        $report = app(InboxExperienceReviewService::class)->review();

        $this->assertSame('excellent', $report['state']);
        $this->assertSame([], $report['blockers']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'inbox_review_completed']);
    }

    public function test_inbox_experience_review_can_report_acceptable_and_improvement_needed(): void
    {
        Config::set('inbox-roadmap.inbox_review.polling_ready', false);
        $this->assertSame('acceptable', app(InboxExperienceReviewService::class)->review()['state']);

        Config::set('inbox-roadmap.inbox_review.usability_ready', false);
        $report = app(InboxExperienceReviewService::class)->review();

        $this->assertSame('improvement-needed', $report['state']);
        $this->assertContains('inbox_route', array_column($report['blockers'], 'name'));
    }

    public function test_mailbox_lifecycle_review_reports_lifecycle_checks_and_event(): void
    {
        $report = app(MailboxLifecycleReviewService::class)->review();

        $this->assertSame('excellent', $report['state']);
        $this->assertContains('mailbox_creation_flow', array_column($report['checks'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'mailbox_review_completed']);
    }

    public function test_message_workflow_review_reports_attachment_warning(): void
    {
        Config::set('inbox-roadmap.message_workflow.attachment_ready', false);

        $report = app(MessageWorkflowReviewService::class)->review();

        $this->assertSame('acceptable', $report['state']);
        $this->assertContains('attachment_flow', array_column($report['warnings'], 'name'));
    }

    public function test_accessibility_review_can_warn_and_records_event(): void
    {
        Config::set('inbox-roadmap.accessibility.screen_reader_ready', false);

        $report = app(InboxAccessibilityReviewService::class)->review();

        $this->assertSame('acceptable', $report['state']);
        $this->assertContains('screen_reader_readiness', array_column($report['warnings'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'accessibility_review_completed']);
    }

    public function test_ux_prioritization_groups_candidates(): void
    {
        $report = app(InboxUXPrioritizationService::class)->report();

        $this->assertGreaterThanOrEqual(5, $report['candidate_count']);
        $this->assertNotEmpty($report['quick_wins']);
        $this->assertNotEmpty($report['high_impact_improvements']);
        $this->assertNotEmpty($report['low_risk_improvements']);
        $this->assertContains('accessibility_pass', array_column($report['quick_wins'], 'key'));
    }

    public function test_inbox_roadmap_planning_aggregates_reviews_and_events(): void
    {
        $report = app(InboxRoadmapPlanningService::class)->report();

        $this->assertSame('excellent', $report['summary']['state']);
        $this->assertArrayHasKey('inbox', $report['reviews']);
        $this->assertArrayHasKey('message_workflow', $report['reviews']);
        $this->assertNotEmpty($report['roadmap']['phase_1']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'inbox_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'inbox_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'mailbox_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'accessibility_review_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'inbox_roadmap_generated']);
    }

    public function test_inbox_roadmap_command_outputs_safe_summary(): void
    {
        $this->artisan('system:inbox-roadmap-status')
            ->expectsOutput('v1.1 inbox roadmap summary')
            ->expectsOutput('Inbox experience: EXCELLENT')
            ->expectsOutput('Mailbox lifecycle: EXCELLENT')
            ->expectsOutput('Message workflow: EXCELLENT')
            ->expectsOutput('Accessibility: EXCELLENT')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->doesntExpectOutputToContain('mailbox@example.test')
            ->assertSuccessful();
    }

    public function test_inbox_roadmap_reports_do_not_expose_mailbox_contents(): void
    {
        Config::set('inbox-roadmap.roadmap.candidates', [
            [
                'key' => 'safe_candidate',
                'title' => 'Improve inbox for mailbox@example.test <b>secret</b>',
                'category' => 'inbox',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
        ]);

        $encoded = json_encode(app(InboxRoadmapPlanningService::class)->report(), JSON_THROW_ON_ERROR);
        $events = OperationsEvent::query()->where('source', 'inbox-roadmap')->get()->toJson();

        $this->assertStringNotContainsString('mailbox@example.test', $encoded);
        $this->assertStringNotContainsString('<b>', $encoded);
        $this->assertStringNotContainsString('mailbox@example.test', $events);
        $this->assertStringNotContainsString('secret', $events);
    }
}
