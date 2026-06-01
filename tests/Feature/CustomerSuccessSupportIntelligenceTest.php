<?php

namespace Tests\Feature;

use App\Enums\SupportCategory;
use App\Enums\SupportPriority;
use App\Enums\SupportStatus;
use App\Models\OperationsEvent;
use App\Models\Organization;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Support\CustomerHealthService;
use App\Services\Support\CustomerSuccessIntelligenceService;
use App\Services\Support\SupportAnalyticsService;
use App\Services\Support\SupportRequestService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerSuccessSupportIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_support_request_migration_model_and_relationships_work(): void
    {
        $this->assertTrue(Schema::hasTable('support_requests'));
        $this->assertTrue(Schema::hasColumns('support_requests', [
            'user_id',
            'organization_id',
            'category',
            'priority',
            'status',
            'subject',
            'message',
            'metadata',
            'first_response_at',
            'resolved_at',
        ]));

        $user = User::factory()->create();
        $organization = Organization::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Support Org',
            'slug' => 'support-org',
            'status' => 'active',
            'owner_user_id' => $user->getKey(),
        ]);
        $request = app(SupportRequestService::class)->create([
            'category' => 'inbox',
            'priority' => 'high',
            'subject' => 'Inbox support',
            'message' => 'Polling needs review.',
        ], $user, $organization);

        $this->assertTrue($request->user->is($user));
        $this->assertTrue($request->organization->is($organization));
        $this->assertTrue($user->supportRequests->contains($request));
        $this->assertTrue($organization->supportRequests->contains($request));
        $this->assertSame(SupportCategory::Inbox, $request->category);
        $this->assertSame(SupportPriority::High, $request->priority);
        $this->assertSame(SupportStatus::Open, $request->status);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'support_request_created']);
    }

    public function test_support_service_classifies_defaults_and_tracks_lifecycle(): void
    {
        $service = app(SupportRequestService::class);
        $classification = $service->classify(['category' => 'unknown', 'priority' => 'unknown']);
        $request = $this->supportRequest();

        $this->assertSame(SupportCategory::Other, $classification['category']);
        $this->assertSame(SupportPriority::Medium, $classification['priority']);
        $this->assertSame(SupportStatus::InProgress, $service->updateStatus($request, SupportStatus::InProgress)->status);
        $this->assertNotNull($request->fresh()->first_response_at);
        $this->assertSame(SupportStatus::Resolved, $service->updateStatus($request, SupportStatus::Resolved)->status);
        $this->assertNotNull($request->fresh()->resolved_at);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'support_request_updated']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'support_request_resolved']);
    }

    public function test_support_service_redacts_personal_data_and_sensitive_metadata(): void
    {
        $request = app(SupportRequestService::class)->create([
            'subject' => '<b>Contact user@example.com</b>',
            'message' => 'Mailbox user@example.com needs review.',
            'metadata' => [
                'request_body' => 'raw-ticket-body',
                'mailbox_address' => 'mailbox@example.test',
                'token' => 'provider-secret',
                'label' => '<i>Reach user@example.com</i>',
            ],
        ]);

        $this->assertSame('Contact [redacted-email]', $request->subject);
        $this->assertSame('Mailbox [redacted-email] needs review.', $request->message);
        $this->assertSame(['label' => 'Reach [redacted-email]'], $request->metadata);

        $event = OperationsEvent::query()->where('event_type', 'support_request_created')->firstOrFail()->toJson();
        $this->assertStringNotContainsString('user@example.com', $event);
        $this->assertStringNotContainsString('raw-ticket-body', $event);
    }

    public function test_support_analytics_reports_aggregate_metrics(): void
    {
        $service = app(SupportRequestService::class);
        $first = $this->supportRequest(category: 'inbox', priority: 'high');
        $this->supportRequest(category: 'billing', priority: 'medium');
        $first->forceFill(['created_at' => now()->subMinutes(30)])->save();
        $service->updateStatus($first, SupportStatus::Resolved);

        $report = app(SupportAnalyticsService::class)->report();

        $this->assertSame(2, $report['total_requests']);
        $this->assertSame(1, $report['open_requests']);
        $this->assertSame(30.0, $report['average_response_minutes']);
        $this->assertSame(30.0, $report['average_resolution_minutes']);
        $this->assertSame(1, $report['category_distribution']['inbox']);
        $this->assertSame(1, $report['priority_distribution']['medium']);
    }

    public function test_customer_health_service_classifies_support_risk_and_records_event(): void
    {
        $this->supportRequest(priority: 'critical');
        $this->supportRequest(priority: 'high');

        $report = app(CustomerHealthService::class)->review();

        $this->assertSame('risk', $report['state']);
        $this->assertGreaterThanOrEqual(5, $report['score']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'customer_health_reviewed']);
    }

    public function test_customer_success_intelligence_identifies_themes_and_retention_risks(): void
    {
        $this->supportRequest(category: 'account', priority: 'high');
        $this->supportRequest(category: 'account', priority: 'medium');

        $report = app(CustomerSuccessIntelligenceService::class)->report();

        $this->assertSame('account', $report['recurring_themes'][0]['category']);
        $this->assertSame('account', $report['onboarding_issues'][0]['category']);
        $this->assertSame('account', $report['retention_risks'][0]['category']);
        $this->assertNotEmpty($report['opportunities']);
        $this->assertNotEmpty($report['recommendations']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'support_insight_generated']);
    }

    public function test_support_intelligence_command_outputs_safe_aggregate_summary(): void
    {
        $this->supportRequest(message: 'Contact private@example.com about mailbox@example.test.');

        $this->artisan('system:support-intelligence')
            ->expectsOutput('Support intelligence summary')
            ->expectsOutput('Open requests: 1')
            ->doesntExpectOutputToContain('private@example.com')
            ->doesntExpectOutputToContain('mailbox@example.test')
            ->assertSuccessful();
    }

    public function test_support_reports_never_expose_ticket_contents(): void
    {
        $this->supportRequest(message: 'raw-ticket-body private@example.com provider-secret');

        $encoded = json_encode(app(CustomerSuccessIntelligenceService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('raw-ticket-body', $encoded);
        $this->assertStringNotContainsString('private@example.com', $encoded);
        $this->assertStringNotContainsString('provider-secret', $encoded);
        $this->assertStringNotContainsString('message', $encoded);
    }

    private function supportRequest(
        string $category = 'inbox',
        string $priority = 'medium',
        string $message = 'A safe support request.',
    ): SupportRequest {
        return app(SupportRequestService::class)->create([
            'category' => $category,
            'priority' => $priority,
            'subject' => 'Support subject',
            'message' => $message,
        ]);
    }
}
