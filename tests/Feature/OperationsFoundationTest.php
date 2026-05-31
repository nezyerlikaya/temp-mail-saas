<?php

namespace Tests\Feature;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\SystemHealthStatus;
use App\Models\DomainHealthCheck;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use App\Services\Operations\DomainHealthService;
use App\Services\Operations\FailedJobMonitorService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Operations\QueueMonitorService;
use App\Services\Operations\SystemMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_migrations_and_model_helpers_work(): void
    {
        $this->assertTrue(Schema::hasTable('operations_events'));
        $this->assertTrue(Schema::hasTable('queue_metrics'));
        $this->assertTrue(Schema::hasTable('domain_health_checks'));

        $event = OperationsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => OperationCategory::Queue,
            'event_type' => 'test_event',
            'severity' => OperationSeverity::Critical,
            'status' => OperationStatus::Detected,
            'metadata' => ['safe' => true],
            'occurred_at' => now(),
        ]);
        $metric = QueueMetric::query()->create([
            'queue_name' => 'default',
            'pending_jobs' => 1,
            'failed_jobs' => 1,
            'processed_jobs' => 0,
            'measured_at' => now(),
        ]);
        $domain = DomainHealthCheck::query()->create([
            'domain' => 'example.test',
            'status' => SystemHealthStatus::Healthy,
            'score' => 100,
            'checked_at' => now(),
        ]);

        $this->assertTrue($event->isCritical());
        $this->assertTrue($event->isQueueEvent());
        $this->assertTrue($metric->hasBacklog());
        $this->assertTrue($metric->hasFailures());
        $this->assertTrue($domain->isHealthy());
    }

    public function test_operations_logger_sanitizes_metadata(): void
    {
        $event = app(OperationsLoggerService::class)->log(
            OperationCategory::System,
            'diagnostic',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'test',
            'Safe message',
            [
                'safe' => 'value',
                'payload' => 'private-payload',
                'api_key' => 'secret-key',
                'nested' => ['token' => 'hidden', 'count' => 1],
            ],
        );

        $this->assertSame(['safe' => 'value', 'nested' => ['count' => 1]], $event->metadata);
        $this->assertStringNotContainsString('private-payload', $event->toJson());
        $this->assertStringNotContainsString('secret-key', $event->toJson());
    }

    public function test_queue_monitor_collects_metrics_and_creates_warning_events(): void
    {
        config([
            'operations.queue_names' => ['default'],
            'operations.thresholds.queue_pending_warning' => 1,
            'operations.thresholds.queue_failed_warning' => 1,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => 'private job payload',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'private failed payload',
            'exception' => 'private exception',
            'failed_at' => now(),
        ]);

        $metrics = app(QueueMonitorService::class)->collect();

        $this->assertSame(1, $metrics[0]['pending_jobs']);
        $this->assertSame(1, $metrics[0]['failed_jobs']);
        $this->assertDatabaseHas('queue_metrics', ['queue_name' => 'default']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'queue_threshold_exceeded']);
        $this->assertStringNotContainsString('private failed payload', OperationsEvent::query()->latest('id')->first()->toJson());
    }

    public function test_domain_health_service_evaluates_configured_domains(): void
    {
        config(['domains.public_mailbox.allowed_domains' => ['example.test']]);

        $checks = app(DomainHealthService::class)->evaluate();

        $this->assertSame('example.test', $checks[0]['domain']);
        $this->assertSame('healthy', $checks[0]['status']);
        $this->assertDatabaseHas('domain_health_checks', ['domain' => 'example.test']);
    }

    public function test_failed_job_monitor_summarizes_without_payload_exposure(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'secret payload',
            'exception' => 'secret exception',
            'failed_at' => now(),
        ]);

        $summary = app(FailedJobMonitorService::class)->summarize();
        $event = OperationsEvent::query()->where('event_type', 'failed_jobs_detected')->firstOrFail();

        $this->assertSame(1, $summary['total_failed_jobs']);
        $this->assertStringNotContainsString('secret payload', $event->toJson());
        $this->assertStringNotContainsString('secret exception', $event->toJson());
    }

    public function test_system_metrics_collection_stores_metrics(): void
    {
        $metrics = app(SystemMetricsService::class)->collect();

        $this->assertArrayHasKey('app', $metrics);
        $this->assertArrayHasKey('queue', $metrics);
        $this->assertArrayHasKey('domains', $metrics);
        $this->assertGreaterThan(0, QueueMetric::query()->count());
        $this->assertGreaterThan(0, DomainHealthCheck::query()->count());
    }

    public function test_operations_commands_work(): void
    {
        $this->artisan('operations:collect-metrics')
            ->expectsOutput('Operations metrics collected.')
            ->expectsOutputToContain('Queue metrics:')
            ->expectsOutputToContain('Domain checks:')
            ->assertSuccessful();

        $this->artisan('operations:health-summary')
            ->expectsOutput('Operations health summary')
            ->expectsOutputToContain('Events:')
            ->expectsOutputToContain('Queue metrics:')
            ->expectsOutputToContain('Domain checks:')
            ->assertSuccessful();
    }

    public function test_existing_routes_still_work(): void
    {
        $this->getJson('/api/v1/ping')->assertUnauthorized();
        $this->get('/login')->assertOk();
        $this->get('/inbox')->assertOk();
        $this->assertContains($this->get('/install')->getStatusCode(), [200, 302]);
        $this->get('/admin')->assertForbidden();
    }
}
