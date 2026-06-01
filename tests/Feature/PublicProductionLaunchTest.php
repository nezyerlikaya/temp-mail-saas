<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Models\QueueMetric;
use App\Services\System\LaunchGateService;
use App\Services\System\LaunchOperationsCertificationService;
use App\Services\System\PostLaunchObservationService;
use App\Services\System\PublicLaunchReadinessService;
use App\Services\System\PublicTrafficReadinessService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicProductionLaunchTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->installerPath = storage_path('framework/testing/public-launch');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('p', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('p', 32)),
            'app.url' => 'https://public-launch.example.test',
            'cache.default' => 'array',
            'queue.default' => 'database',
            'mail.default' => 'array',
            'production.server_readiness.required_extensions' => [],
            'production.public_launch.provider' => 'mailgun',
            'production.public_launch.domain' => 'launch-mail.test',
            'production.first_24_hours.review.queue_metrics_required' => true,
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.public_mailbox.default_domain' => 'fallback-launch.test',
            'domains.public_mailbox.allowed_domains' => ['launch-mail.test', 'fallback-launch.test'],
            'domains.live_activation.rollback.fallback_domain' => 'fallback-launch.test',
            'features-gates.plans.free.allowed_domains' => ['launch-mail.test', 'fallback-launch.test'],
            'mail-providers.staging.allowed_domains' => ['launch-mail.test', 'fallback-launch.test'],
            'mail-providers.activation.safety.require_staging_passed' => false,
            'mail-providers.activation.safety.require_webhook_ready' => false,
            'mail-providers.activation.safety.require_queue_ready' => false,
            'mail-providers.activation.safety.require_installer_ready' => false,
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.activation.readiness.providers' => ['mailgun'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'public-launch-secret',
            'inbound.queue.name' => 'inbound-mail',
            'load-testing.thresholds.operations_recent_metric_minimum' => 0,
        ]);

        $this->domain('launch-mail.test');
        $this->domain('fallback-launch.test');
        QueueMetric::query()->create([
            'queue_name' => 'inbound-mail',
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'processed_jobs' => 1,
            'measured_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_public_launch_readiness_service_reports_ready_state(): void
    {
        $report = app(PublicLaunchReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame('certified', $report['certification']['status']);
        $this->assertSame('ready', $report['gates']['status']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'public_launch_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'public_launch_review_ready']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'public_launch_certified']);
    }

    public function test_launch_gate_service_classifies_blockers_and_warnings(): void
    {
        $report = app(LaunchGateService::class)->evaluate([
            'security' => ['blockers' => [['name' => 'security_regression', 'message' => 'Security regression.']]],
            'support' => ['warnings' => [['name' => 'coverage', 'message' => 'Review coverage.']]],
        ]);

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('security_regression', array_column($report['blockers'], 'name'));
        $this->assertContains('coverage', array_column($report['warnings'], 'name'));
    }

    public function test_public_traffic_readiness_checks_polling_queue_abuse_and_monitoring(): void
    {
        $report = app(PublicTrafficReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertContains('polling_readiness', array_column($report['passed'], 'name'));
        $this->assertContains('queue_readiness', array_column($report['passed'], 'name'));
        $this->assertContains('abuse_protection', array_column($report['passed'], 'name'));
        $this->assertContains('monitoring_readiness', array_column($report['passed'], 'name'));
    }

    public function test_launch_operations_certification_reports_certified_state(): void
    {
        $report = app(LaunchOperationsCertificationService::class)->certify();

        $this->assertSame('certified', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame('healthy', $report['monitoring']['status']);
        $this->assertSame('safe', $report['rollback']['status']);
    }

    public function test_post_launch_observation_service_defines_first_week_plan(): void
    {
        $plan = app(PostLaunchObservationService::class)->plan();

        $this->assertSame(7, $plan['window_days']);
        $this->assertContains('providers', $plan['monitoring_priorities']);
        $this->assertContains('critical_incident_open', $plan['rollback_triggers']);
        $this->assertContains('review_support_queue', $plan['operational_checkpoints']);
    }

    public function test_public_launch_command_outputs_safe_summary(): void
    {
        $this->artisan('system:public-launch-status')
            ->expectsOutput('Public launch status: READY')
            ->expectsOutput('Certification: CERTIFIED')
            ->expectsOutput('Launch gates: READY')
            ->expectsOutput('Observation window: 7 days')
            ->doesntExpectOutputToContain('public-launch-secret')
            ->assertSuccessful();
    }

    public function test_public_launch_command_fails_when_abuse_protection_is_disabled(): void
    {
        config(['abuse.enabled' => false]);

        $this->artisan('system:public-launch-status')
            ->expectsOutput('Public launch status: BLOCKED')
            ->expectsOutputToContain('Blocker: traffic.public_traffic')
            ->doesntExpectOutputToContain('public-launch-secret')
            ->assertFailed();
    }

    public function test_public_launch_command_fails_when_provider_is_inactive(): void
    {
        config(['mail-providers.activation.states.mailgun' => 'inactive']);

        $this->artisan('system:public-launch-status')
            ->expectsOutput('Public launch status: BLOCKED')
            ->expectsOutputToContain('Blocker:')
            ->assertFailed();
    }

    private function domain(string $name): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => $name,
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
