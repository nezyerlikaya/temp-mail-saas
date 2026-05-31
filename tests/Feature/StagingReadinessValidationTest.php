<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Mail\ProviderConnectivityValidationService;
use App\Services\System\StagingReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StagingReadinessValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerPath = storage_path('framework/testing/staging-readiness');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('s', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.key' => 'base64:'.base64_encode(str_repeat('s', 32)),
            'mail-providers.staging.mode' => true,
            'mail-providers.staging.allowed_domains' => ['example.test'],
            'mail-providers.staging.metrics_enabled' => true,
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'staging-mailgun-key',
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.postmark.signing_key' => 'staging-postmark-key',
            'mail-providers.providers.ses.enabled' => true,
            'mail-providers.providers.ses.signing_key' => 'staging-ses-key',
            'domains.public_mailbox.default_domain' => 'example.test',
            'domains.public_mailbox.allowed_domains' => ['example.test'],
            'inbound.queue.name' => 'inbound-mail',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_incomplete_install_redirects_browser_surfaces_to_installer(): void
    {
        File::delete($this->installerPath.'/install.lock');

        foreach (['/login', '/register', '/dashboard', '/admin', '/admin/login', '/inbox'] as $path) {
            $this->get($path)
                ->assertRedirect('/install');
        }
    }

    public function test_incomplete_install_returns_installer_required_for_api_and_webhooks(): void
    {
        File::delete($this->installerPath.'/install.lock');

        $this->getJson('/api/v1/ping')
            ->assertStatus(423)
            ->assertJson(['status' => 'installer_required']);

        $this->postJson('/billing/webhooks/local', [])
            ->assertStatus(423)
            ->assertJson(['status' => 'installer_required']);

        $this->postJson('/webhooks/mailgun', [])
            ->assertStatus(423)
            ->assertJson(['status' => 'installer_required']);
    }

    public function test_environment_recovery_awareness_blocks_when_app_key_missing(): void
    {
        File::put($this->installerPath.'/.env', 'APP_KEY='.PHP_EOL);

        $this->get('/login')->assertRedirect('/install');
    }

    public function test_provider_connectivity_validation_reports_readiness(): void
    {
        $report = app(ProviderConnectivityValidationService::class)->report('mailgun');
        $names = array_column($report['checks'], 'name');

        $this->assertSame([], $report['blockers']);
        $this->assertContains('mailgun_provider_configured', $names);
        $this->assertContains('mailgun_provider_activation_state', $names);
        $this->assertContains('mailgun_webhook_route_ready', $names);
        $this->assertContains('mailgun_signing_configuration_ready', $names);
        $this->assertContains('mailgun_intake_queue_ready', $names);
    }

    public function test_provider_activation_checks_warn_when_disabled(): void
    {
        config(['mail-providers.providers.mailgun.enabled' => false]);

        $report = app(ProviderConnectivityValidationService::class)->report('mailgun');

        $this->assertContains('mailgun_provider_activation_state', array_column($report['warnings'], 'name'));
    }

    public function test_webhook_readiness_checks_block_missing_configuration(): void
    {
        config(['mail-providers.providers.mailgun.class' => null]);

        $report = app(ProviderConnectivityValidationService::class)->report('mailgun');

        $this->assertContains('mailgun_provider_configured', array_column($report['blockers'], 'name'));
        $this->assertDatabaseHas('operations_events', [
            'event_type' => 'staging_provider_blocked',
            'source' => 'provider-staging',
        ]);
    }

    public function test_staging_readiness_service_reports_warning_state_with_disabled_provider(): void
    {
        config(['mail-providers.providers.postmark.enabled' => false]);

        $status = app(StagingReadinessService::class)->evaluate();

        $this->assertSame('warning', $status['state']);
        $this->assertContains('postmark_provider_activation_state', array_column($status['warnings'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'staging_validation_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'staging_validation_passed']);
    }

    public function test_staging_readiness_blocks_on_installer_lock_missing(): void
    {
        File::delete($this->installerPath.'/install.lock');

        $status = app(StagingReadinessService::class)->evaluate();

        $this->assertSame('blocked', $status['state']);
        $this->assertContains('installation_healthy', array_column($status['blockers'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'staging_validation_failed']);
    }

    public function test_domain_and_queue_readiness_checks_are_present(): void
    {
        $status = app(StagingReadinessService::class)->evaluate();
        $names = array_column($status['checks'], 'name');

        $this->assertContains('staging_allowed_domains_configured', $names);
        $this->assertContains('domain_pool_fallback_available', $names);
        $this->assertContains('inbound_queue_ready', $names);
        $this->assertContains('database_ready', $names);
        $this->assertContains('cache_ready', $names);
    }

    public function test_staging_command_outputs_safe_summary(): void
    {
        $this->artisan('system:staging-readiness')
            ->expectsOutput('Staging readiness: READY')
            ->expectsOutputToContain('Blockers:')
            ->expectsOutputToContain('Warnings:')
            ->expectsOutputToContain('Recommendations:')
            ->doesntExpectOutputToContain('staging-mailgun-key')
            ->assertSuccessful();
    }

    public function test_staging_command_fails_when_blocked(): void
    {
        File::delete($this->installerPath.'/install.lock');

        $this->artisan('system:staging-readiness')
            ->expectsOutput('Staging readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: installation_healthy')
            ->assertFailed();
    }
}
