<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Services\System\BetaFeedbackReadinessService;
use App\Services\System\IssueTriageService;
use App\Services\System\PublicBetaCertificationService;
use App\Services\System\PublicBetaReadinessService;
use App\Services\System\SupportReadinessService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->installerPath = storage_path('framework/testing/public-beta');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('b', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
            'app.url' => 'https://beta.example.test',
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
            'domains.public_mailbox.default_domain' => 'beta-mail.test',
            'domains.public_mailbox.allowed_domains' => ['beta-mail.test'],
            'features-gates.plans.free.allowed_domains' => ['beta-mail.test'],
            'mail-providers.staging.allowed_domains' => ['beta-mail.test'],
            'mail-providers.activation.safety.require_staging_passed' => true,
            'mail-providers.activation.safety.require_webhook_ready' => true,
            'mail-providers.activation.safety.require_queue_ready' => true,
            'mail-providers.activation.safety.require_installer_ready' => true,
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.activation.readiness.providers' => ['mailgun'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'beta-mailgun-secret',
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.postmark.signing_key' => 'beta-postmark-secret',
            'mail-providers.providers.ses.enabled' => true,
            'mail-providers.providers.ses.signing_key' => 'beta-ses-secret',
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

    public function test_beta_readiness_service_reports_ready(): void
    {
        $report = app(PublicBetaReadinessService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertContains('registration_flow_complete', array_column($report['checks'], 'name'));
        $this->assertContains('domain_onboarding_compatible', array_column($report['checks'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'beta_readiness_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'beta_readiness_ready']);
    }

    public function test_support_readiness_service_can_warn(): void
    {
        config(['production.public_beta.support.escalation_paths_documented' => false]);

        $report = app(SupportReadinessService::class)->report();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('escalation_paths', array_column($report['warnings'], 'name'));
        $this->assertNotEmpty($report['recommendations']);
    }

    public function test_issue_triage_service_classifies_severity_owner_and_priority(): void
    {
        $triage = app(IssueTriageService::class)->classify('critical', 'provider', 'Webhook outage');

        $this->assertSame('critical', $triage['severity']);
        $this->assertSame('provider', $triage['owner']);
        $this->assertSame(1, $triage['priority']);
        $this->assertStringContainsString('Immediate', $triage['response']);

        $fallback = app(IssueTriageService::class)->classify('unknown', 'unknown');
        $this->assertSame('medium', $fallback['severity']);
        $this->assertSame('support', $fallback['owner']);
    }

    public function test_feedback_readiness_service_reports_warning_without_external_integration(): void
    {
        config(['production.public_beta.feedback.issue_intake_documented' => false]);

        $report = app(BetaFeedbackReadinessService::class)->report();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('issue_intake', array_column($report['warnings'], 'name'));
    }

    public function test_public_beta_certification_service_certifies_ready_state(): void
    {
        $report = app(PublicBetaCertificationService::class)->report();

        $this->assertSame('certified', $report['status']);
        $this->assertSame('public-beta', $report['target']);
        $this->assertSame([], $report['blockers']);
        $this->assertArrayHasKey('readiness', $report);
        $this->assertArrayHasKey('rc3', $report);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'beta_certification_completed']);
    }

    public function test_public_beta_certification_warning_and_blocked_states_work(): void
    {
        config(['production.public_beta.feedback.collection_documented' => false]);
        $warning = app(PublicBetaCertificationService::class)->report();
        $this->assertSame('warning', $warning['status']);
        $this->assertContains('feedback_collection', array_column($warning['warnings'], 'name'));

        Domain::query()->delete();
        $blocked = app(PublicBetaCertificationService::class)->report();
        $this->assertSame('blocked', $blocked['status']);
        $this->assertContains('domain_onboarding_compatible', array_column($blocked['blockers'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'beta_readiness_blocked']);
    }

    public function test_public_beta_command_outputs_safe_summary(): void
    {
        $this->artisan('system:public-beta-status')
            ->expectsOutput('Public beta status: CERTIFIED')
            ->expectsOutput('Target: public-beta')
            ->expectsOutputToContain('Beta readiness:')
            ->expectsOutputToContain('RC3 certification:')
            ->doesntExpectOutputToContain('beta-mailgun-secret')
            ->assertSuccessful();
    }

    public function test_public_beta_command_fails_when_blocked(): void
    {
        Domain::query()->delete();

        $this->artisan('system:public-beta-status')
            ->expectsOutput('Public beta status: BLOCKED')
            ->expectsOutputToContain('Blocker: support.domain_onboarding_compatible')
            ->assertFailed();
    }

    public function test_readiness_aggregation_contains_support_feedback_monitoring_and_incident_sections(): void
    {
        $sections = app(PublicBetaReadinessService::class)->report()['sections'];

        $this->assertArrayHasKey('onboarding', $sections);
        $this->assertArrayHasKey('support', $sections);
        $this->assertArrayHasKey('feedback', $sections);
        $this->assertArrayHasKey('monitoring', $sections);
        $this->assertArrayHasKey('incident', $sections);
    }

    private function domain(): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => 'beta-mail.test',
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
