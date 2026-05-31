<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Services\System\FinalReleaseStatusService;
use App\Services\System\LaunchSignOffService;
use App\Services\System\PostLaunchMonitoringService;
use App\Services\System\V1LaunchCertificationService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class V1LaunchCertificationTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->installerPath = storage_path('framework/testing/v1-launch');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('v', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('v', 32)),
            'app.url' => 'https://v1.example.test',
            'queue.default' => 'database',
            'mail.default' => 'array',
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
            'production.rc3.provider' => 'mailgun',
            'production.rc3.domain' => null,
            'production.rc3.mailbox' => null,
            'production.rc3.require_active_domain' => true,
            'production.public_beta.require_rc3_certified' => true,
            'production.public_beta.support.runbooks_documented' => true,
            'production.public_beta.support.escalation_paths_documented' => true,
            'production.public_beta.support.troubleshooting_guidance_documented' => true,
            'production.public_beta.feedback.collection_documented' => true,
            'production.public_beta.feedback.issue_intake_documented' => true,
            'production.public_beta.feedback.operational_response_documented' => true,
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.public_mailbox.default_domain' => 'v1-mail.test',
            'domains.public_mailbox.allowed_domains' => ['v1-mail.test'],
            'features-gates.plans.free.allowed_domains' => ['v1-mail.test'],
            'mail-providers.staging.allowed_domains' => ['v1-mail.test'],
            'mail-providers.activation.safety.require_staging_passed' => true,
            'mail-providers.activation.safety.require_webhook_ready' => true,
            'mail-providers.activation.safety.require_queue_ready' => true,
            'mail-providers.activation.safety.require_installer_ready' => true,
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.activation.readiness.providers' => ['mailgun'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'v1-mailgun-secret',
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.postmark.signing_key' => 'v1-postmark-secret',
            'mail-providers.providers.ses.enabled' => true,
            'mail-providers.providers.ses.signing_key' => 'v1-ses-secret',
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

    public function test_v1_launch_certification_service_certifies_ready_state(): void
    {
        $report = app(V1LaunchCertificationService::class)->report();

        $this->assertSame('certified', $report['status']);
        $this->assertSame('v1.0.0', $report['target']);
        $this->assertSame([], $report['blockers']);
        $this->assertArrayHasKey('rc3', $report['sections']);
        $this->assertArrayHasKey('public_beta', $report['sections']);
        $this->assertArrayHasKey('sign_off', $report['sections']);
        $this->assertArrayHasKey('post_launch_monitoring', $report['sections']);
    }

    public function test_final_release_status_service_returns_launch_ready_decision(): void
    {
        $status = app(FinalReleaseStatusService::class)->evaluate();

        $this->assertSame('ready', $status['status']);
        $this->assertSame('high', $status['confidence']);
        $this->assertSame('Production Launch Ready', $status['launch_decision']);
        $this->assertTrue($status['rollback']['ready']);
        $this->assertSame('ready', $status['post_launch']['status']);
    }

    public function test_launch_sign_off_service_lists_required_areas_and_manual_notes(): void
    {
        $checklist = app(LaunchSignOffService::class)->checklist(['security' => 'Approved by launch commander']);

        $this->assertSame('pass', $checklist['status']);
        $this->assertContains('security', array_column($checklist['areas'], 'area'));
        $this->assertContains('rollback', array_column($checklist['areas'], 'area'));
        $this->assertSame('Approved by launch commander', $checklist['areas'][0]['manual_note']);
    }

    public function test_post_launch_monitoring_service_lists_critical_signals_and_triggers(): void
    {
        $plan = app(PostLaunchMonitoringService::class)->plan();

        $this->assertSame('ready', $plan['status']);
        $this->assertSame(24, $plan['window_hours']);
        $this->assertContains('queue_backlog', $plan['critical_signals']);
        $this->assertContains('provider_failures', $plan['critical_signals']);
        $this->assertContains('critical_incident_open', $plan['rollback_triggers']);
        $this->assertNotEmpty($plan['operator_guidance']);
    }

    public function test_blocker_and_warning_classification_work(): void
    {
        config(['monitoring.enabled' => false]);
        $warning = app(V1LaunchCertificationService::class)->report();
        $this->assertSame('warning', $warning['status']);

        config(['app.debug' => true]);
        $blocked = app(V1LaunchCertificationService::class)->report();
        $this->assertSame('blocked', $blocked['status']);
        $this->assertNotEmpty($blocked['blockers']);
    }

    public function test_v1_launch_status_command_outputs_safe_summary(): void
    {
        $this->artisan('system:v1-launch-status')
            ->expectsOutput('v1.0.0 launch status: READY')
            ->expectsOutput('Target: v1.0.0')
            ->expectsOutput('Decision: Production Launch Ready')
            ->expectsOutputToContain('Post-launch window: 24 hours')
            ->doesntExpectOutputToContain('v1-mailgun-secret')
            ->assertSuccessful();
    }

    public function test_v1_launch_status_command_fails_when_blocked(): void
    {
        config(['app.debug' => true]);

        $this->artisan('system:v1-launch-status')
            ->expectsOutput('v1.0.0 launch status: BLOCKED')
            ->expectsOutputToContain('Blocker:')
            ->assertFailed();
    }

    private function domain(): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => 'v1-mail.test',
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
