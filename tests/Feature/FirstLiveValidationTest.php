<?php

namespace Tests\Feature;

use App\Services\System\FirstLiveSmokeTestService;
use App\Services\System\ProductionEnvironmentValidationService;
use App\Services\System\ServerReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FirstLiveValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerPath = storage_path('framework/testing/first-live');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('f', 32)),
            'app.url' => 'https://example.test',
            'queue.default' => 'database',
            'mail.default' => 'array',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'production.first_live_validation.require_installer_lock' => true,
            'production.first_live_validation.warn_on_sync_queue' => true,
            'production.first_live_validation.warn_on_log_mailer' => true,
            'production.server_readiness.minimum_php_version' => '8.2.0',
            'production.server_readiness.scheduler_required' => false,
            'production.server_readiness.queue_worker_required' => false,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_production_environment_validation_service_reports_safe_results(): void
    {
        $report = app(ProductionEnvironmentValidationService::class)->report();
        $names = array_column($report['checks'], 'name');

        $this->assertSame([], $report['blockers']);
        $this->assertContains('app_env', $names);
        $this->assertContains('database_connectivity', $names);
        $this->assertContains('cache_store', $names);
        $this->assertContains('installer_lock', $names);
    }

    public function test_environment_validation_detects_blockers_and_warnings(): void
    {
        File::delete($this->installerPath.'/install.lock');
        config([
            'app.env' => 'local',
            'app.debug' => true,
            'app.key' => null,
            'queue.default' => 'sync',
            'mail.default' => 'log',
        ]);

        $report = app(ProductionEnvironmentValidationService::class)->report();

        $this->assertContains('app_env', array_column($report['blockers'], 'name'));
        $this->assertContains('app_debug', array_column($report['blockers'], 'name'));
        $this->assertContains('app_key', array_column($report['blockers'], 'name'));
        $this->assertContains('installer_lock', array_column($report['blockers'], 'name'));
        $this->assertContains('queue_driver', array_column($report['warnings'], 'name'));
        $this->assertContains('mail_configuration', array_column($report['warnings'], 'name'));
    }

    public function test_server_readiness_service_validates_runtime_and_paths(): void
    {
        $report = app(ServerReadinessService::class)->report();
        $names = array_column($report['checks'], 'name');

        $this->assertSame([], $report['blockers']);
        $this->assertContains('php_version', $names);
        $this->assertContains('php_extensions', $names);
        $this->assertContains('writable_paths', $names);
        $this->assertContains('scheduler_readiness', $names);
        $this->assertContains('queue_worker_readiness', $names);
    }

    public function test_server_readiness_can_block_on_queue_worker_requirement(): void
    {
        config([
            'production.server_readiness.queue_worker_required' => true,
            'queue.default' => 'sync',
        ]);

        $report = app(ServerReadinessService::class)->report();

        $this->assertContains('queue_worker_readiness', array_column($report['blockers'], 'name'));
    }

    public function test_first_live_smoke_test_service_validates_routes_and_protection(): void
    {
        $report = app(FirstLiveSmokeTestService::class)->report();
        $names = array_column($report['checks'], 'name');

        $this->assertSame([], $report['blockers']);
        $this->assertContains('homepage', $names);
        $this->assertContains('health', $names);
        $this->assertContains('status', $names);
        $this->assertContains('installer_lock_behavior', $names);
        $this->assertContains('api_ping_protection', $names);
        $this->assertContains('admin_protection', $names);
    }

    public function test_first_live_command_outputs_safe_summary(): void
    {
        $this->artisan('system:first-live-check')
            ->expectsOutput('First-live status: READY')
            ->expectsOutputToContain('Passed:')
            ->expectsOutput('Warnings: 0')
            ->expectsOutput('Blockers: 0')
            ->assertSuccessful();
    }

    public function test_first_live_command_fails_when_blocked_and_does_not_leak_secrets(): void
    {
        File::delete($this->installerPath.'/install.lock');
        config([
            'app.key' => 'super-secret-app-key',
            'database.connections.sqlite.password' => 'super-secret-db-password',
        ]);

        $this->artisan('system:first-live-check')
            ->expectsOutput('First-live status: BLOCKED')
            ->expectsOutputToContain('Blocker: environment.installer_lock')
            ->doesntExpectOutputToContain('super-secret-app-key')
            ->doesntExpectOutputToContain('super-secret-db-password')
            ->assertFailed();
    }

    public function test_installer_lock_validation_can_be_relaxed_for_recovery(): void
    {
        File::delete($this->installerPath.'/install.lock');
        config(['production.first_live_validation.require_installer_lock' => false]);

        $environment = app(ProductionEnvironmentValidationService::class)->report();
        $smoke = app(FirstLiveSmokeTestService::class)->report();

        $this->assertNotContains('installer_lock', array_column($environment['blockers'], 'name'));
        $this->assertNotContains('installer_lock_behavior', array_column($smoke['blockers'], 'name'));
    }
}
