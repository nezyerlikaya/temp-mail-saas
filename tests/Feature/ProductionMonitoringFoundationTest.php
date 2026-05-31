<?php

namespace Tests\Feature;

use App\Enums\BillingProvider;
use App\Enums\BillingWebhookStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\MonitoringAlertStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\ApiUsageLog;
use App\Models\BillingWebhookEvent;
use App\Models\Incident;
use App\Models\MonitoringAlert;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use App\Services\Operations\IncidentService;
use App\Services\Operations\MonitoringService;
use App\Services\Operations\UptimeReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionMonitoringFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_and_alert_migrations_work(): void
    {
        $this->assertTrue(Schema::hasTable('incidents'));
        $this->assertTrue(Schema::hasColumns('incidents', [
            'uuid',
            'category',
            'severity',
            'status',
            'title',
            'summary',
            'detected_at',
            'resolved_at',
            'metadata',
        ]));

        $this->assertTrue(Schema::hasTable('monitoring_alerts'));
        $this->assertTrue(Schema::hasColumns('monitoring_alerts', [
            'uuid',
            'source',
            'alert_type',
            'severity',
            'status',
            'message',
            'triggered_at',
            'acknowledged_at',
            'resolved_at',
        ]));
    }

    public function test_incident_lifecycle_and_metadata_sanitization_work(): void
    {
        $service = app(IncidentService::class);

        $incident = $service->create('provider', IncidentSeverity::Critical, 'Provider outage', 'Mail provider failures detected.', [
            'provider' => 'mailgun',
            'secret_token' => 'hidden',
        ]);

        $this->assertTrue($incident->isOpen());
        $this->assertTrue($incident->isCritical());
        $this->assertSame(['provider' => 'mailgun'], $incident->metadata);

        $incident = $service->acknowledge($incident);
        $this->assertTrue($incident->isAcknowledged());

        $incident = $service->resolve($incident);
        $this->assertTrue($incident->isResolved());
        $this->assertNotNull($incident->resolved_at);
    }

    public function test_alert_creation_deduplicates_and_critical_alerts_create_incidents(): void
    {
        config(['monitoring.incidents.create_for_critical_alerts' => true]);
        $service = app(MonitoringService::class);

        $first = $service->createAlert('queue', 'queue_lag', IncidentSeverity::Critical, 'Queue lag is critical.');
        $second = $service->createAlert('queue', 'queue_lag', IncidentSeverity::Critical, 'Queue lag is still critical.');

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($first->isActive());
        $this->assertTrue($first->isCritical());
        $this->assertSame(1, MonitoringAlert::query()->count());
        $this->assertSame(1, Incident::query()->where('severity', IncidentSeverity::Critical)->count());
    }

    public function test_queue_monitoring_creates_alerts(): void
    {
        config([
            'monitoring.thresholds.queue_pending_warning' => 5,
            'monitoring.thresholds.queue_failed_warning' => 1,
        ]);
        QueueMetric::query()->create([
            'queue_name' => 'inbound-mail',
            'pending_jobs' => 8,
            'failed_jobs' => 1,
            'processed_jobs' => 0,
            'measured_at' => now(),
        ]);

        $report = app(MonitoringService::class)->reviewQueues();

        $this->assertSame('alerts', $report['status']);
        $this->assertDatabaseHas('monitoring_alerts', ['source' => 'queue', 'alert_type' => 'queue_lag']);
        $this->assertDatabaseHas('monitoring_alerts', ['source' => 'queue', 'alert_type' => 'failed_job_spike']);
    }

    public function test_provider_monitoring_creates_alerts(): void
    {
        config(['monitoring.thresholds.provider_failure_warning' => 1]);
        $this->operationEvent('provider_intake_failed');

        $report = app(MonitoringService::class)->reviewProviders();

        $this->assertSame(1, $report['failures']);
        $this->assertDatabaseHas('monitoring_alerts', ['source' => 'provider', 'alert_type' => 'provider_failures']);
    }

    public function test_billing_monitoring_creates_alerts(): void
    {
        config(['monitoring.thresholds.billing_webhook_failure_warning' => 1]);
        BillingWebhookEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => BillingProvider::Local,
            'event_id' => 'evt-monitoring-1',
            'event_type' => 'invoice.payment_failed',
            'signature_valid' => false,
            'status' => BillingWebhookStatus::Failed,
            'payload_hash' => hash('sha256', 'safe'),
            'failed_at' => now(),
        ]);

        $report = app(MonitoringService::class)->reviewBilling();

        $this->assertSame(1, $report['failures']);
        $this->assertDatabaseHas('monitoring_alerts', ['source' => 'billing', 'alert_type' => 'billing_webhook_failures']);
    }

    public function test_api_monitoring_creates_alerts(): void
    {
        config([
            'monitoring.thresholds.api_usage_spike_warning' => 10,
            'monitoring.thresholds.api_failure_warning' => 3,
        ]);
        ApiUsageLog::query()->create([
            'endpoint' => '/api/v1/ping',
            'method' => 'GET',
            'response_status' => 500,
            'request_count' => 12,
            'occurred_at' => now(),
        ]);

        $report = app(MonitoringService::class)->reviewApi();

        $this->assertSame(12, $report['usage']);
        $this->assertSame(12, $report['failures']);
        $this->assertDatabaseHas('monitoring_alerts', ['source' => 'api', 'alert_type' => 'api_usage_spike']);
        $this->assertDatabaseHas('monitoring_alerts', ['source' => 'api', 'alert_type' => 'api_failures']);
    }

    public function test_uptime_readiness_reports_internal_monitoring_readiness(): void
    {
        $report = app(UptimeReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertTrue($report['checks']['health_endpoint']);
        $this->assertTrue($report['checks']['incident_tracking']);
        $this->assertTrue($report['checks']['alert_tracking']);
    }

    public function test_monitoring_commands_work(): void
    {
        MonitoringAlert::query()->create([
            'uuid' => (string) Str::uuid(),
            'source' => 'queue',
            'alert_type' => 'queue_lag',
            'severity' => IncidentSeverity::Medium,
            'status' => MonitoringAlertStatus::Active,
            'message' => 'Queue lag warning.',
            'triggered_at' => now(),
        ]);
        Incident::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => 'queue',
            'severity' => IncidentSeverity::High,
            'status' => IncidentStatus::Open,
            'title' => 'Queue backlog',
            'detected_at' => now(),
        ]);

        $this->artisan('monitoring:health-review --no-evaluate')
            ->expectsOutput('Monitoring health review')
            ->expectsOutput('Active alerts: 1')
            ->expectsOutput('Open incidents: 1')
            ->assertExitCode(0);

        $this->artisan('monitoring:incident-review')
            ->expectsOutput('Monitoring incident review')
            ->expectsOutput('Open incidents: 1')
            ->expectsOutput('Critical incidents: 0')
            ->assertExitCode(0);
    }

    private function operationEvent(string $eventType): OperationsEvent
    {
        return OperationsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => OperationCategory::Mail,
            'event_type' => $eventType,
            'severity' => OperationSeverity::Warning,
            'status' => OperationStatus::Detected,
            'source' => 'inbound-provider',
            'message' => 'Provider monitoring test event.',
            'metadata' => ['provider' => 'mailgun'],
            'occurred_at' => now(),
        ]);
    }
}
