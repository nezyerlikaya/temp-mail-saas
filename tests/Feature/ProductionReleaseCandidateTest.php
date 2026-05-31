<?php

namespace Tests\Feature;

use App\Services\System\ProductionReadinessChecklistService;
use App\Services\System\ReleaseStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionReleaseCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_config_contains_release_readiness_options(): void
    {
        $this->assertIsArray(config('production.release'));
        $this->assertIsArray(config('production.deployment.required_writable_paths'));
        $this->assertIsArray(config('production.monitoring'));
        $this->assertSame('rc1', config('production.release.target'));
    }

    public function test_release_readiness_service_reports_deployment_backup_monitoring_and_go_live_checks(): void
    {
        config([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('p', 32)),
            'app.url' => 'https://example.test',
            'queue.default' => 'database',
            'mail.default' => 'array',
            'production.monitoring.operations_metrics_required' => false,
        ]);

        $report = app(ProductionReadinessChecklistService::class)->report();
        $names = array_column($report['checks'], 'name');

        $this->assertContains('app_key_configured', $names);
        $this->assertContains('storage_paths_writable', $names);
        $this->assertContains('backup_readiness', $names);
        $this->assertContains('health_routes_registered', $names);
        $this->assertContains('system_health_available', $names);
        $this->assertContains('installer_route_available', $names);
        $this->assertArrayHasKey('blockers', $report);
        $this->assertArrayHasKey('warnings', $report);
        $this->assertArrayHasKey('recommendations', $report);
    }

    public function test_blocker_classification_works(): void
    {
        config([
            'app.debug' => true,
            'production.release.block_on_debug' => true,
        ]);

        $status = app(ReleaseStatusService::class)->evaluate();

        $this->assertSame('blocked', $status['state']);
        $this->assertContains('debug_disabled', array_column($status['blockers'], 'name'));
    }

    public function test_warning_classification_works(): void
    {
        config([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('w', 32)),
            'app.url' => 'http://example.test',
            'queue.default' => 'sync',
            'mail.default' => 'log',
            'production.release.block_on_debug' => true,
            'production.release.warn_on_http_url' => true,
            'production.release.warn_on_sync_queue' => true,
            'production.release.warn_on_log_mailer' => true,
        ]);

        $status = app(ReleaseStatusService::class)->evaluate();

        $this->assertSame('warning', $status['state']);
        $this->assertContains('app_url_https', array_column($status['warnings'], 'name'));
        $this->assertContains('queue_driver_ready', array_column($status['warnings'], 'name'));
        $this->assertContains('mail_transport_ready', array_column($status['warnings'], 'name'));
    }

    public function test_ready_classification_works_when_warnings_are_disabled_or_resolved(): void
    {
        config([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)),
            'app.url' => 'https://example.test',
            'queue.default' => 'database',
            'mail.default' => 'array',
            'production.monitoring.operations_metrics_required' => false,
        ]);

        $status = app(ReleaseStatusService::class)->evaluate();

        $this->assertSame('ready', $status['state']);
        $this->assertSame([], $status['blockers']);
        $this->assertSame([], $status['warnings']);
    }

    public function test_release_status_command_outputs_safe_summary(): void
    {
        config([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('c', 32)),
            'app.url' => 'https://example.test',
            'queue.default' => 'database',
            'mail.default' => 'array',
        ]);

        $this->artisan('system:release-status')
            ->expectsOutput('Release status: READY')
            ->expectsOutput('Target: rc1')
            ->expectsOutputToContain('Blockers:')
            ->expectsOutputToContain('Warnings:')
            ->expectsOutputToContain('Recommendations:')
            ->assertSuccessful();
    }

    public function test_release_status_command_fails_when_blocked(): void
    {
        config([
            'app.debug' => true,
            'production.release.block_on_debug' => true,
        ]);

        $this->artisan('system:release-status')
            ->expectsOutput('Release status: BLOCKED')
            ->expectsOutputToContain('Blocker: debug_disabled')
            ->assertFailed();
    }

    public function test_monitoring_routes_are_registered_for_release(): void
    {
        $this->assertTrue(Route::has('health'));
        $this->assertTrue(Route::has('status'));

        $this->get('/health')->assertOk();
        $this->get('/status')->assertOk();
        $this->get('/up')->assertOk();
    }
}
