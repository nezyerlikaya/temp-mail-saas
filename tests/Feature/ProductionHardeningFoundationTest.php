<?php

namespace Tests\Feature;

use App\Enums\SystemHealthStatus;
use App\Models\SystemHealthCheck;
use App\Services\System\BackupReadinessService;
use App\Services\System\ErrorTrackingService;
use App\Services\System\ProductionReadinessService;
use App\Services\System\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ProductionHardeningFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_health_checks_migration_and_model_helpers_work(): void
    {
        $this->assertTrue(Schema::hasTable('system_health_checks'));
        $this->assertTrue(Schema::hasColumns('system_health_checks', [
            'uuid',
            'check_name',
            'status',
            'message',
            'metadata',
            'checked_at',
        ]));

        $check = SystemHealthCheck::query()->create([
            'uuid' => (string) Str::uuid(),
            'check_name' => 'database',
            'status' => SystemHealthStatus::Healthy,
            'message' => 'OK',
            'metadata' => ['safe' => true],
            'checked_at' => now(),
        ]);

        $this->assertTrue($check->isHealthy());
        $this->assertFalse($check->isWarning());
        $this->assertFalse($check->isCritical());
    }

    public function test_health_service_returns_structured_safe_results_and_stores_records(): void
    {
        $report = app(SystemHealthService::class)->run(store: true);
        $encoded = json_encode($report);

        $this->assertArrayHasKey('status', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertNotEmpty($report['checks']);
        $this->assertDatabaseCount('system_health_checks', count($report['checks']));
        $this->assertStringNotContainsString((string) config('app.key'), $encoded);
        $this->assertStringNotContainsString('DB_PASSWORD', $encoded);
        $this->assertStringNotContainsString('MAIL_PASSWORD', $encoded);
    }

    public function test_production_readiness_service_reports_pass_warnings_and_failures(): void
    {
        config([
            'app.debug' => false,
            'app.url' => 'https://example.test',
            'mail.default' => 'array',
            'queue.default' => 'database',
        ]);

        $report = app(ProductionReadinessService::class)->report();

        $this->assertSame(0, $report['failures']);
        $this->assertGreaterThanOrEqual(1, $report['passed']);
        $this->assertArrayHasKey('warnings', $report);
        $this->assertArrayHasKey('checks', $report);
    }

    public function test_health_command_creates_records_and_outputs_safe_summary(): void
    {
        $this->artisan('system:health-check')
            ->expectsOutputToContain('System health status:')
            ->expectsOutputToContain('database:')
            ->assertSuccessful();

        $this->assertGreaterThan(0, SystemHealthCheck::query()->count());
    }

    public function test_readiness_command_outputs_safe_summary(): void
    {
        config([
            'app.debug' => false,
            'app.url' => 'https://example.test',
            'mail.default' => 'array',
            'queue.default' => 'database',
        ]);

        $this->artisan('system:readiness-check')
            ->expectsOutput('Production readiness summary')
            ->expectsOutputToContain('Passed:')
            ->expectsOutputToContain('Warnings:')
            ->expectsOutputToContain('Failures:')
            ->assertSuccessful();
    }

    public function test_error_tracking_service_uses_local_logging_fallback_and_sanitizes_context(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $encoded = json_encode($context);

                return $message === 'Application error reported.'
                    && str_contains($encoded, 'RuntimeException')
                    && ! str_contains($encoded, 'secret-value')
                    && ! str_contains($encoded, 'private-body');
            });

        $result = app(ErrorTrackingService::class)->report(new RuntimeException('Small failure'), [
            'safe' => 'value',
            'password' => 'secret-value',
            'payload' => 'private-body',
        ]);

        $this->assertTrue($result['reported']);
        $this->assertSame('log', $result['provider']);
    }

    public function test_backup_readiness_service_reports_without_creating_archives(): void
    {
        $report = app(BackupReadinessService::class)->report();

        $this->assertArrayHasKey('ready', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertNotEmpty($report['checks']);
        $this->assertContains('backup_paths_readable', array_column($report['checks'], 'name'));
    }

    public function test_existing_routes_still_work(): void
    {
        $this->get('/health')->assertOk();
        $this->getJson('/api/v1/ping')->assertUnauthorized();
        $this->get('/inbox')->assertOk();
        $this->get('/login')->assertOk();
        $this->assertContains($this->get('/install')->getStatusCode(), [200, 302]);
        $this->get('/admin')->assertForbidden();
    }
}
