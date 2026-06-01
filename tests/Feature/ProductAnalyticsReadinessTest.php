<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Analytics\AnalyticsCertificationService;
use App\Services\Analytics\AnalyticsReadinessService;
use App\Services\Analytics\ConversionFunnelReadinessService;
use App\Services\Analytics\RetentionReadinessService;
use App\Services\Analytics\UserJourneyReadinessService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAnalyticsReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_analytics_readiness_service_reports_ready_state(): void
    {
        $report = app(AnalyticsReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame('certified', $report['certification']['status']);
        $this->assertArrayHasKey('analytics', $report['sections']);
        $this->assertArrayHasKey('conversion', $report['sections']);
        $this->assertArrayHasKey('journey', $report['sections']);
        $this->assertArrayHasKey('retention', $report['sections']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'analytics_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'analytics_review_ready']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'analytics_certified']);
    }

    public function test_conversion_funnel_review_can_warn_without_personal_profiling(): void
    {
        config(['analytics.conversion.premium_conversion' => false]);

        $report = app(ConversionFunnelReadinessService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('premium_conversion', array_column($report['warnings'], 'name'));
        $this->assertStringNotContainsString('profile', json_encode($report, JSON_THROW_ON_ERROR));
    }

    public function test_user_journey_review_can_warn_on_support_gap(): void
    {
        config(['analytics.journeys.support' => false]);

        $report = app(UserJourneyReadinessService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('support_journey', array_column($report['warnings'], 'name'));
    }

    public function test_retention_readiness_can_warn_on_revisit_gap(): void
    {
        config(['analytics.retention.revisit' => false]);

        $report = app(RetentionReadinessService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('revisit_readiness', array_column($report['warnings'], 'name'));
    }

    public function test_analytics_certification_blocks_when_analytics_is_blocked(): void
    {
        $analytics = ['blockers' => [['name' => 'privacy_readiness', 'message' => 'Privacy blocked.']]];

        $report = app(AnalyticsCertificationService::class)->certify($analytics);

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('analytics_readiness', array_column($report['blockers'], 'name'));
    }

    public function test_analytics_command_outputs_safe_summary(): void
    {
        $this->artisan('system:analytics-status')
            ->expectsOutput('Analytics readiness: READY')
            ->expectsOutput('Certification: CERTIFIED')
            ->doesntExpectOutputToContain('test@example.com')
            ->doesntExpectOutputToContain('fingerprint')
            ->assertSuccessful();
    }

    public function test_analytics_command_fails_when_privacy_is_disabled(): void
    {
        config(['analytics.privacy.allow_email_addresses' => true]);

        $this->artisan('system:analytics-status')
            ->expectsOutput('Analytics readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: analytics.privacy_readiness')
            ->doesntExpectOutputToContain('test@example.com')
            ->assertFailed();
    }

    public function test_analytics_events_and_reports_do_not_leak_pii(): void
    {
        $report = app(AnalyticsReadinessService::class)->report();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertTrue(OperationsEvent::query()->where('event_type', 'analytics_review_ready')->exists());
        $this->assertStringNotContainsString('test@example.com', $encoded);
        $this->assertStringNotContainsString('mailbox@example.test', $encoded);
        $this->assertStringNotContainsString('raw-email-body', $encoded);
        $this->assertStringNotContainsString('device_fingerprint', $encoded);
    }
}
