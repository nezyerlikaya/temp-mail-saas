<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Services\System\LaunchBlockerReviewService;
use App\Services\System\RC3CertificationService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class RC3CertificationTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->installerPath = storage_path('framework/testing/rc3-certification');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('r', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)),
            'app.url' => 'https://rc3.example.test',
            'queue.default' => 'database',
            'mail.default' => 'array',
            'production.rc3.provider' => 'mailgun',
            'production.rc3.domain' => null,
            'production.rc3.mailbox' => null,
            'production.rc3.require_active_domain' => true,
            'production.server_readiness.required_extensions' => [],
            'production.monitoring.operations_metrics_required' => false,
            'production.backup.restore_prerequisites_documented' => true,
            'production.backup.retention_guidance_documented' => true,
            'production.deployment.checklists.shared_hosting' => true,
            'production.deployment.checklists.vps' => true,
            'production.deployment.checklists.queue_workers' => true,
            'production.deployment.checklists.scheduler' => true,
            'production.launch.require_monitoring_ready' => true,
            'production.launch.require_backup_ready' => true,
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.public_mailbox.default_domain' => 'rc3-mail.test',
            'domains.public_mailbox.allowed_domains' => ['rc3-mail.test'],
            'features-gates.plans.free.allowed_domains' => ['rc3-mail.test'],
            'mail-providers.staging.allowed_domains' => ['rc3-mail.test'],
            'mail-providers.activation.safety.require_staging_passed' => true,
            'mail-providers.activation.safety.require_webhook_ready' => true,
            'mail-providers.activation.safety.require_queue_ready' => true,
            'mail-providers.activation.safety.require_installer_ready' => true,
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.activation.readiness.providers' => ['mailgun'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'rc3-mailgun-secret',
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.postmark.signing_key' => 'rc3-postmark-secret',
            'mail-providers.providers.ses.enabled' => true,
            'mail-providers.providers.ses.signing_key' => 'rc3-ses-secret',
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

    public function test_rc3_certification_service_can_certify_ready_application(): void
    {
        $report = app(RC3CertificationService::class)->report();

        $this->assertSame('certified', $report['status']);
        $this->assertSame('rc3', $report['target']);
        $this->assertSame([], $report['blockers']);
        $this->assertArrayHasKey('security', $report['sections']);
        $this->assertArrayHasKey('staging', $report['sections']);
        $this->assertArrayHasKey('first_real_mail', $report['sections']);
        $this->assertArrayHasKey('load', $report['sections']);
        $this->assertArrayHasKey('go_live', $report['sections']);
    }

    public function test_certification_states_warning_and_blocked_work(): void
    {
        config(['monitoring.enabled' => false]);
        $warning = app(RC3CertificationService::class)->report();

        $this->assertSame('warning', $warning['status']);
        $this->assertContains('monitoring_enabled', array_column($warning['warnings'], 'name'));

        config(['app.debug' => true]);
        $blocked = app(RC3CertificationService::class)->report();

        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('secret_protection', array_column($blocked['blockers'], 'name'));
    }

    public function test_launch_blocker_review_classifies_severity_ownership_and_recommendations(): void
    {
        $review = app(LaunchBlockerReviewService::class)->review([
            'security' => [
                'blockers' => [['name' => 'secret_protection', 'message' => 'Debug mode must be disabled.']],
                'warnings' => [],
            ],
            'billing' => [
                'blockers' => [],
                'warnings' => [['name' => 'billing_review', 'message' => 'Review billing readiness.']],
            ],
        ]);

        $this->assertSame('blocked', $review['status']);
        $this->assertSame('security', $review['blockers'][0]['category']);
        $this->assertSame('security', $review['blockers'][0]['owner']);
        $this->assertSame('warning', $review['warnings'][0]['severity']);
        $this->assertNotEmpty($review['recommendations']);
    }

    public function test_rc3_certification_records_observability_events(): void
    {
        app(RC3CertificationService::class)->report();

        $this->assertDatabaseHas('operations_events', ['event_type' => 'rc3_certification_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'rc3_certification_passed']);

        config(['app.debug' => true]);
        app(RC3CertificationService::class)->report();

        $this->assertDatabaseHas('operations_events', ['event_type' => 'rc3_certification_blocked']);
    }

    public function test_certification_command_outputs_safe_summary(): void
    {
        $this->artisan('system:rc3-certification')
            ->expectsOutput('RC3 certification: CERTIFIED')
            ->expectsOutput('Target: rc3')
            ->expectsOutputToContain('Blockers:')
            ->expectsOutputToContain('Warnings:')
            ->doesntExpectOutputToContain('rc3-mailgun-secret')
            ->assertSuccessful();
    }

    public function test_certification_command_fails_when_blocked(): void
    {
        config(['app.debug' => true]);

        $this->artisan('system:rc3-certification')
            ->expectsOutput('RC3 certification: BLOCKED')
            ->expectsOutputToContain('Blocker: security.secret_protection')
            ->assertFailed();
    }

    public function test_readiness_aggregation_covers_operational_and_security_reviews(): void
    {
        $sections = app(RC3CertificationService::class)->report()['sections'];
        $security = array_column($sections['security']['checks'], 'name');
        $operations = array_column($sections['operations']['checks'], 'name');
        $systems = array_column($sections['systems']['checks'], 'name');

        $this->assertContains('authorization_coverage', $security);
        $this->assertContains('csrf_protection', $security);
        $this->assertContains('api_protection', $security);
        $this->assertContains('webhook_protection', $security);
        $this->assertContains('installer_lock', $operations);
        $this->assertContains('backup_readiness', $operations);
        $this->assertContains('rollback_readiness', $operations);
        $this->assertContains('system_automation', $systems);
    }

    private function domain(): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => 'rc3-mail.test',
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
