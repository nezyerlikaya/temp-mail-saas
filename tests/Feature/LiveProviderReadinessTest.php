<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Services\Mail\LiveProviderReadinessService;
use App\Services\Mail\ProviderRollbackReadinessService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveProviderReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->installerPath = storage_path('framework/testing/live-provider');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('l', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.key' => 'base64:'.base64_encode(str_repeat('l', 32)),
            'queue.default' => 'database',
            'mail.default' => 'array',
            'mail-providers.live_activation.providers' => ['mailgun', 'postmark', 'ses'],
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.activation.states.postmark' => 'active',
            'mail-providers.activation.states.ses' => 'active',
            'mail-providers.activation.readiness.providers' => ['mailgun', 'postmark', 'ses'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'live-mailgun-secret',
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.postmark.signing_key' => 'live-postmark-secret',
            'mail-providers.providers.ses.enabled' => true,
            'mail-providers.providers.ses.signing_key' => 'live-ses-secret',
            'mail-providers.staging.allowed_domains' => ['mailgun-live.test', 'postmark-live.test', 'ses-live.test'],
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.public_mailbox.default_domain' => 'mailgun-live.test',
            'domains.public_mailbox.allowed_domains' => ['mailgun-live.test', 'postmark-live.test', 'ses-live.test'],
            'features-gates.plans.free.allowed_domains' => ['mailgun-live.test', 'postmark-live.test', 'ses-live.test'],
            'inbound.queue.name' => 'inbound-mail',
            'load-testing.thresholds.operations_recent_metric_minimum' => 0,
        ]);

        $this->domain('mailgun-live.test', 'mailgun');
        $this->domain('postmark-live.test', 'postmark');
        $this->domain('ses-live.test', 'ses');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_live_provider_readiness_service_returns_ready_state(): void
    {
        $report = app(LiveProviderReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertArrayHasKey('mailgun', $report['sections']);
        $this->assertArrayHasKey('postmark', $report['sections']);
        $this->assertArrayHasKey('ses', $report['sections']);
        $this->assertSame([], $report['rollback']['blockers']);
    }

    public function test_provider_rollback_readiness_service_reviews_fallback_and_queue_safety(): void
    {
        $report = app(ProviderRollbackReadinessService::class)->report('mailgun');

        $this->assertSame('local', $report['fallback_provider']);
        $this->assertSame([], $report['blockers']);
        $this->assertContains('queue_safety_review', array_column($report['passed'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'live_provider_rollback_reviewed']);
    }

    public function test_provider_compatibility_and_webhook_activation_checks_are_present(): void
    {
        $report = app(LiveProviderReadinessService::class)->report('mailgun');
        $checks = array_column($report['sections']['mailgun']['checks'], 'name');

        $this->assertContains('mailgun_webhook_route_registered', $checks);
        $this->assertContains('mailgun_installer_enforcement', $checks);
        $this->assertContains('mailgun_signature_verification', $checks);
        $this->assertContains('mailgun_replay_protection', $checks);
        $this->assertContains('mailgun_duplicate_protection', $checks);
        $this->assertContains('mailgun_queue_first_handoff', $checks);
        $this->assertContains('mailgun_provider_mapping_valid', $checks);
        $this->assertContains('mailgun_mailbox_generation_compatibility', $checks);
    }

    public function test_live_provider_readiness_blocks_inactive_provider_and_records_audits(): void
    {
        config(['mail-providers.activation.states.mailgun' => 'inactive']);

        $report = app(LiveProviderReadinessService::class)->report('mailgun');

        $this->assertSame('blocked', $report['status']);
        $this->assertNotEmpty($report['blockers']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'live_provider_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'live_provider_review_blocked']);
        $this->assertDatabaseHas('provider_activation_audits', [
            'provider' => 'mailgun',
            'review_type' => 'readiness_review',
        ]);
        $this->assertDatabaseHas('provider_activation_audits', [
            'provider' => 'mailgun',
            'review_type' => 'activation_recommendation',
        ]);
        $this->assertDatabaseHas('provider_activation_audits', [
            'provider' => 'mailgun',
            'review_type' => 'suspension_recommendation',
        ]);
    }

    public function test_live_provider_readiness_command_outputs_safe_summary(): void
    {
        $this->artisan('provider:live-readiness --provider=mailgun')
            ->expectsOutput('Live provider readiness: READY')
            ->expectsOutput('Providers: mailgun')
            ->expectsOutput('Blockers: 0')
            ->doesntExpectOutputToContain('live-mailgun-secret')
            ->assertSuccessful();
    }

    public function test_live_provider_readiness_command_fails_when_queue_is_not_worker_backed(): void
    {
        config(['queue.default' => 'sync']);

        $this->artisan('provider:live-readiness --provider=mailgun')
            ->expectsOutput('Live provider readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: mailgun.mailgun_queue_first_handoff')
            ->doesntExpectOutputToContain('live-mailgun-secret')
            ->assertFailed();
    }

    private function domain(string $domain, string $provider): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => $domain,
            'status' => DomainStatus::Active,
            'onboarding_state' => DomainOnboardingState::Active,
            'type' => DomainType::Public,
            'tier' => DomainTier::Free,
            'priority' => 10,
            'health_score' => 100,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'metadata' => [
                'onboarding' => [
                    'provider' => $provider,
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
