<?php

namespace Tests\Feature;

use App\Enums\BillingProvider;
use App\Enums\BillingWebhookStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\ApiUsageLog;
use App\Models\BillingWebhookEvent;
use App\Models\Incident;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use App\Services\Operations\First24HourMonitoringService;
use App\Services\Operations\LaunchDayIncidentService;
use App\Services\Operations\LaunchMonitoringSummaryService;
use App\Services\Operations\RollbackTriggerReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class First24HourMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'operations.queue_names' => ['inbound-mail'],
            'production.first_24_hours.review.queue_metrics_required' => false,
            'production.first_24_hours.thresholds.queue_pending_warning' => 5,
            'production.first_24_hours.thresholds.queue_pending_critical' => 10,
            'production.first_24_hours.thresholds.queue_failed_warning' => 1,
            'production.first_24_hours.thresholds.queue_failed_critical' => 5,
            'production.first_24_hours.thresholds.provider_failures_warning' => 2,
            'production.first_24_hours.thresholds.provider_failures_critical' => 5,
            'production.first_24_hours.rollback.provider_failure_threshold' => 5,
            'production.first_24_hours.rollback.queue_pending_threshold' => 10,
            'production.first_24_hours.rollback.queue_failed_threshold' => 5,
        ]);
    }

    public function test_first_24_hour_monitoring_service_reports_healthy_state(): void
    {
        QueueMetric::query()->create([
            'queue_name' => 'inbound-mail',
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'processed_jobs' => 10,
            'measured_at' => now(),
        ]);

        $report = app(First24HourMonitoringService::class)->report();

        $this->assertSame('healthy', $report['status']);
        $this->assertSame([], $report['critical']);
        $this->assertSame('safe', $report['rollback']['status']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'launch_monitoring_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'launch_monitoring_healthy']);
    }

    public function test_launch_day_incident_service_classifies_escalation_and_rollback(): void
    {
        $this->incident('provider', IncidentSeverity::Critical, 'Provider outage');

        $review = app(LaunchDayIncidentService::class)->review();

        $this->assertSame('critical', $review['status']);
        $this->assertSame(1, $review['critical_count']);
        $this->assertSame(1, $review['categories']['provider']);
        $this->assertNotEmpty($review['escalation_recommendations']);
        $this->assertNotEmpty($review['rollback_recommendations']);
    }

    public function test_rollback_trigger_service_recommends_rollback_on_thresholds(): void
    {
        QueueMetric::query()->create([
            'queue_name' => 'inbound-mail',
            'pending_jobs' => 12,
            'failed_jobs' => 6,
            'processed_jobs' => 0,
            'measured_at' => now(),
        ]);
        $this->providerFailure();
        $this->providerFailure();
        $this->providerFailure();
        $this->providerFailure();
        $this->providerFailure();

        $review = app(RollbackTriggerReviewService::class)->review();

        $this->assertSame('rollback-recommended', $review['status']);
        $this->assertContains('queue_pending', array_column($review['rollback_triggers'], 'name'));
        $this->assertContains('provider_failures', array_column($review['rollback_triggers'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'launch_monitoring_rollback_reviewed']);
    }

    public function test_monitoring_summary_service_summarizes_recommendations(): void
    {
        $this->incident('queue', IncidentSeverity::High, 'Queue backlog');

        $report = app(First24HourMonitoringService::class)->report();
        $summary = app(LaunchMonitoringSummaryService::class)->summarize($report);

        $this->assertSame('warning', $summary['status']);
        $this->assertSame('warning', $summary['incident_status']);
        $this->assertGreaterThan(0, $summary['warning_count']);
        $this->assertNotEmpty($summary['recommendations']);
    }

    public function test_monitoring_command_outputs_safe_summary(): void
    {
        QueueMetric::query()->create([
            'queue_name' => 'inbound-mail',
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'processed_jobs' => 1,
            'measured_at' => now(),
        ]);

        $this->artisan('system:launch-monitoring-status')
            ->expectsOutput('Launch monitoring status: HEALTHY')
            ->expectsOutput('Rollback review: SAFE')
            ->doesntExpectOutputToContain('secret-token')
            ->assertSuccessful();
    }

    public function test_monitoring_command_fails_on_critical_state_without_secret_leakage(): void
    {
        $this->incident('operations', IncidentSeverity::Critical, 'Secret incident', [
            'secret_token' => 'secret-token',
        ]);

        $this->artisan('system:launch-monitoring-status')
            ->expectsOutput('Launch monitoring status: CRITICAL')
            ->expectsOutputToContain('Critical:')
            ->doesntExpectOutputToContain('secret-token')
            ->assertFailed();
    }

    public function test_readiness_aggregation_covers_billing_api_and_provider_warnings(): void
    {
        $this->providerFailure();
        $this->providerFailure();
        BillingWebhookEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => BillingProvider::Local,
            'event_type' => 'invoice.failed',
            'signature_valid' => false,
            'status' => BillingWebhookStatus::Failed,
            'payload_hash' => hash('sha256', 'safe'),
            'failed_at' => now(),
        ]);
        ApiUsageLog::query()->create([
            'endpoint' => '/api/v1/ping',
            'method' => 'GET',
            'response_status' => 500,
            'request_count' => 25,
            'occurred_at' => now(),
        ]);

        $report = app(First24HourMonitoringService::class)->report();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('provider_failures', array_column($report['sections']['provider']['warnings'], 'name'));
        $this->assertContains('billing_failures', array_column($report['sections']['billing']['warnings'], 'name'));
        $this->assertContains('api_failures', array_column($report['sections']['api']['warnings'], 'name'));
    }

    private function providerFailure(): OperationsEvent
    {
        return OperationsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => OperationCategory::Mail,
            'event_type' => 'provider_intake_failed',
            'severity' => OperationSeverity::Warning,
            'status' => OperationStatus::Detected,
            'source' => 'inbound-provider',
            'message' => 'Provider failure test event.',
            'metadata' => ['provider' => 'mailgun'],
            'occurred_at' => now(),
        ]);
    }

    private function incident(string $category, IncidentSeverity $severity, string $title, array $metadata = []): Incident
    {
        return Incident::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => $category,
            'severity' => $severity,
            'status' => IncidentStatus::Open,
            'title' => $title,
            'detected_at' => now(),
            'metadata' => $metadata,
        ]);
    }
}
