<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Models\OperationsEvent;
use App\Services\System\ProductionDeploymentReadinessService;
use App\Services\System\ProductionEnvironmentValidationService;
use App\Services\System\ServerProfileValidationService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionDeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->installerPath = storage_path('framework/testing/deployment-readiness');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::ensureDirectoryExists(storage_path('framework/cache'));
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('d', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('d', 32)),
            'app.url' => 'https://deployment.example.test',
            'queue.default' => 'database',
            'mail.default' => 'array',
            'production.server_readiness.required_extensions' => [],
            'production.deployment_readiness.provider.name' => 'mailgun',
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.public_mailbox.default_domain' => 'deployment-mail.test',
            'domains.public_mailbox.allowed_domains' => ['deployment-mail.test'],
            'features-gates.plans.free.allowed_domains' => ['deployment-mail.test'],
            'mail-providers.staging.allowed_domains' => ['deployment-mail.test'],
            'mail-providers.activation.safety.require_staging_passed' => true,
            'mail-providers.activation.safety.require_webhook_ready' => true,
            'mail-providers.activation.safety.require_queue_ready' => true,
            'mail-providers.activation.safety.require_installer_ready' => true,
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.activation.readiness.providers' => ['mailgun'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'deployment-mailgun-secret',
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.postmark.signing_key' => 'deployment-postmark-secret',
            'mail-providers.providers.ses.enabled' => true,
            'mail-providers.providers.ses.signing_key' => 'deployment-ses-secret',
            'inbound.queue.name' => 'inbound-mail',
            'load-testing.thresholds.operations_recent_metric_minimum' => 0,
        ]);

        $this->domain();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_server_profile_validation_is_ready_for_worker_deployment(): void
    {
        $report = app(ServerProfileValidationService::class)->report();

        $this->assertSame([], $report['blockers']);
        $this->assertContains('queue_driver_readiness', array_column($report['passed'], 'name'));
        $this->assertContains('bootstrap_cache_permissions', array_column($report['passed'], 'name'));
    }

    public function test_environment_validation_blocks_debug_mode_without_exposing_app_key(): void
    {
        config(['app.debug' => true]);

        $report = app(ProductionEnvironmentValidationService::class)->report();

        $this->assertContains('app_debug', array_column($report['blockers'], 'name'));
        $this->assertStringNotContainsString('deployment-mailgun-secret', json_encode($report));
    }

    public function test_deployment_readiness_aggregates_ready_sections_and_records_events(): void
    {
        $report = app(ProductionDeploymentReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertArrayHasKey('server', $report['sections']);
        $this->assertArrayHasKey('environment', $report['sections']);
        $this->assertArrayHasKey('queue', $report['sections']);
        $this->assertArrayHasKey('scheduler', $report['sections']);
        $this->assertArrayHasKey('provider', $report['sections']);
        $this->assertArrayHasKey('domain', $report['sections']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'deployment_readiness_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'deployment_readiness_ready']);
    }

    public function test_deployment_readiness_classifies_warning_and_blocked_states(): void
    {
        config(['production.deployment_readiness.queue.restart_strategy_documented' => false]);
        $warning = app(ProductionDeploymentReadinessService::class)->report();
        $this->assertSame('warning', $warning['status']);

        config(['queue.default' => 'sync']);
        $blocked = app(ProductionDeploymentReadinessService::class)->report();
        $this->assertSame('blocked', $blocked['status']);
        $this->assertNotEmpty($blocked['blockers']);
        $this->assertTrue(OperationsEvent::query()->where('event_type', 'deployment_readiness_blocked')->exists());
    }

    public function test_deployment_readiness_command_outputs_safe_summary(): void
    {
        $this->artisan('system:deployment-readiness')
            ->expectsOutput('Production deployment readiness: READY')
            ->expectsOutput('Blockers: 0')
            ->expectsOutput('Warnings: 0')
            ->doesntExpectOutputToContain('deployment-mailgun-secret')
            ->assertSuccessful();
    }

    public function test_deployment_readiness_command_fails_when_provider_is_inactive(): void
    {
        config(['mail-providers.activation.states.mailgun' => 'inactive']);

        $this->artisan('system:deployment-readiness')
            ->expectsOutput('Production deployment readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: provider.provider_activation')
            ->assertFailed();
    }

    private function domain(): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => 'deployment-mail.test',
            'status' => DomainStatus::Active,
            'onboarding_state' => DomainOnboardingState::Active,
            'type' => DomainType::Public,
            'tier' => DomainTier::Free,
            'priority' => 10,
            'health_score' => 100,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'metadata' => [
                'onboarding' => [
                    'provider' => 'mailgun',
                    'dns_readiness' => [
                        'mx' => true,
                        'spf' => true,
                        'dkim' => true,
                        'dmarc' => true,
                        'provider_mapping' => true,
                    ],
                ],
            ],
        ]);
    }
}
