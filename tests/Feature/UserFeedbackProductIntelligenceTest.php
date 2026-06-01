<?php

namespace Tests\Feature;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\OperationsEvent;
use App\Models\User;
use App\Models\UserFeedback;
use App\Services\ProductIntelligence\FeedbackService;
use App\Services\ProductIntelligence\ProductIntelligenceService;
use App\Services\ProductIntelligence\RoadmapInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserFeedbackProductIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_migration_model_and_user_relationship_work(): void
    {
        $this->assertTrue(Schema::hasTable('user_feedback'));
        $this->assertTrue(Schema::hasColumns('user_feedback', [
            'user_id',
            'type',
            'category',
            'priority',
            'status',
            'title',
            'message',
            'metadata',
        ]));

        $user = User::factory()->create();
        $feedback = app(FeedbackService::class)->create([
            'type' => 'issue',
            'category' => 'inbox',
            'priority' => 'high',
            'title' => 'Inbox refresh issue',
            'message' => 'Polling needs review.',
        ], $user);

        $this->assertTrue($feedback->user->is($user));
        $this->assertTrue($user->feedback->contains($feedback));
        $this->assertSame(FeedbackType::Issue, $feedback->type);
        $this->assertSame(FeedbackCategory::Inbox, $feedback->category);
        $this->assertSame(FeedbackPriority::High, $feedback->priority);
        $this->assertSame(FeedbackStatus::New, $feedback->status);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'feedback_created']);
    }

    public function test_feedback_classification_uses_safe_defaults(): void
    {
        $classification = app(FeedbackService::class)->classify([
            'type' => 'unknown',
            'category' => 'unknown',
            'priority' => 'unknown',
        ]);

        $this->assertSame(FeedbackType::Suggestion, $classification['type']);
        $this->assertSame(FeedbackCategory::Other, $classification['category']);
        $this->assertSame(FeedbackPriority::Medium, $classification['priority']);
    }

    public function test_feedback_status_updates_record_review_and_close_events(): void
    {
        $service = app(FeedbackService::class);
        $feedback = $this->feedback();

        $this->assertSame(FeedbackStatus::Reviewed, $service->updateStatus($feedback, FeedbackStatus::Reviewed)->status);
        $this->assertSame(FeedbackStatus::Closed, $service->updateStatus($feedback, FeedbackStatus::Closed)->status);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'feedback_reviewed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'feedback_closed']);
    }

    public function test_feedback_service_redacts_personal_data_and_sensitive_metadata(): void
    {
        $feedback = app(FeedbackService::class)->create([
            'title' => '<b>Contact user@example.com</b>',
            'message' => 'Mailbox user@example.com needs review.',
            'metadata' => [
                'email' => 'user@example.com',
                'mailbox_address' => 'mailbox@example.test',
                'payload' => 'raw-email-body',
                'token' => 'provider-secret',
                'label' => '<i>Reach user@example.com</i>',
            ],
        ]);

        $this->assertSame('Contact [redacted-email]', $feedback->title);
        $this->assertSame('Mailbox [redacted-email] needs review.', $feedback->message);
        $this->assertSame(['label' => 'Reach [redacted-email]'], $feedback->metadata);

        $event = OperationsEvent::query()->where('event_type', 'feedback_created')->firstOrFail()->toJson();
        $this->assertStringNotContainsString('user@example.com', $event);
        $this->assertStringNotContainsString('raw-email-body', $event);
    }

    public function test_feedback_aggregation_and_product_intelligence_identify_trends(): void
    {
        $this->feedback(type: 'issue', category: 'inbox', priority: 'high');
        $this->feedback(type: 'issue', category: 'inbox', priority: 'medium');
        $this->feedback(type: 'feature_request', category: 'billing', priority: 'medium');

        $report = app(ProductIntelligenceService::class)->report();

        $this->assertSame(3, $report['feedback']['total']);
        $this->assertSame(3, $report['feedback']['open']);
        $this->assertSame('inbox', $report['trends'][0]['category']);
        $this->assertSame(2, $report['trends'][0]['count']);
        $this->assertSame('inbox', $report['recurring_issues'][0]['category']);
        $this->assertSame('billing', $report['feature_requests'][0]['category']);
        $this->assertNotEmpty($report['recommendations']);
    }

    public function test_roadmap_insight_service_classifies_demand_and_risks(): void
    {
        $this->feedback(type: 'feature_request', category: 'billing');
        $this->feedback(type: 'feature_request', category: 'billing');
        $this->feedback(type: 'issue', category: 'inbox', priority: 'critical');

        $report = app(RoadmapInsightService::class)->generate();

        $this->assertSame('billing', $report['candidates'][0]['category']);
        $this->assertSame('medium', $report['candidates'][0]['demand_level']);
        $this->assertSame('inbox', $report['risks'][0]['category']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'roadmap_insight_generated']);
    }

    public function test_product_intelligence_command_outputs_safe_aggregate_summary(): void
    {
        $this->feedback(message: 'Contact private@example.com about mailbox@example.test.');

        $this->artisan('system:product-intelligence')
            ->expectsOutput('Product intelligence summary')
            ->expectsOutput('Feedback total: 1')
            ->expectsOutput('Open feedback: 1')
            ->doesntExpectOutputToContain('private@example.com')
            ->doesntExpectOutputToContain('mailbox@example.test')
            ->assertSuccessful();
    }

    public function test_product_intelligence_reports_never_expose_feedback_messages(): void
    {
        $this->feedback(message: 'raw-email-body private@example.com provider-secret');

        $encoded = json_encode(app(ProductIntelligenceService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('raw-email-body', $encoded);
        $this->assertStringNotContainsString('private@example.com', $encoded);
        $this->assertStringNotContainsString('provider-secret', $encoded);
        $this->assertStringNotContainsString('message', $encoded);
    }

    private function feedback(
        string $type = 'suggestion',
        string $category = 'platform',
        string $priority = 'medium',
        string $message = 'A safe feedback message.',
    ): UserFeedback {
        return app(FeedbackService::class)->create([
            'type' => $type,
            'category' => $category,
            'priority' => $priority,
            'title' => 'Feedback title',
            'message' => $message,
        ]);
    }
}
