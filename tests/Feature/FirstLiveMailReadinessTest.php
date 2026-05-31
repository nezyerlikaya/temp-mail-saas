<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\InboundIntakeStatus;
use App\Enums\RetentionTier;
use App\Models\Domain;
use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Services\Mail\FirstLiveMailDiagnosticsService;
use App\Services\Mail\FirstLiveMailReadinessService;
use App\Services\Mail\MailReceptionTraceService;
use App\Services\Mail\PublicInboxMessageService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class FirstLiveMailReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->installerPath = storage_path('framework/testing/first-live-mail');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('m', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.key' => 'base64:'.base64_encode(str_repeat('m', 32)),
            'queue.default' => 'database',
            'mail.default' => 'array',
            'mail-providers.first_live_mail.provider' => 'mailgun',
            'mail-providers.first_live_mail.domain' => 'live-mail.test',
            'mail-providers.activation.safety.require_staging_passed' => false,
            'mail-providers.activation.safety.require_webhook_ready' => false,
            'mail-providers.activation.safety.require_queue_ready' => false,
            'mail-providers.activation.safety.require_installer_ready' => false,
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.activation.readiness.providers' => ['mailgun'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'first-live-secret',
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.public_mailbox.default_domain' => 'fallback-mail.test',
            'domains.public_mailbox.allowed_domains' => ['live-mail.test', 'fallback-mail.test'],
            'domains.live_activation.rollback.fallback_domain' => 'fallback-mail.test',
            'features-gates.plans.free.allowed_domains' => ['live-mail.test', 'fallback-mail.test'],
            'mail-providers.staging.allowed_domains' => ['live-mail.test', 'fallback-mail.test'],
            'inbound.queue.name' => 'inbound-mail',
            'load-testing.thresholds.operations_recent_metric_minimum' => 0,
        ]);

        $this->domain('live-mail.test');
        $this->domain('fallback-mail.test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_first_live_mail_readiness_service_reports_ready_trace(): void
    {
        [$intake] = $this->flow();

        $report = app(FirstLiveMailReadinessService::class)->report(
            provider: 'mailgun',
            domain: 'live-mail.test',
            mailbox: 'probe@live-mail.test',
            intakeUuid: $intake->uuid,
        );

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame('ready', $report['trace']['status']);
        $this->assertTrue($report['trace']['summary']['inbox_visible']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'first_live_mail_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'first_live_mail_review_ready']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'first_live_mail_trace_reviewed']);
    }

    public function test_trace_review_reports_safe_lifecycle_without_payload_leakage(): void
    {
        [$intake] = $this->flow();

        $review = app(MailReceptionTraceService::class)->readinessReview(intakeUuid: $intake->uuid);
        $encoded = json_encode($review, JSON_THROW_ON_ERROR);

        $this->assertSame('ready', $review['status']);
        $this->assertTrue($review['summary']['intake_accepted']);
        $this->assertTrue($review['summary']['intake_queued']);
        $this->assertTrue($review['summary']['intake_processed']);
        $this->assertTrue($review['summary']['message_stored']);
        $this->assertTrue($review['summary']['inbox_visible']);
        $this->assertStringNotContainsString('raw-secret-header', $encoded);
        $this->assertStringNotContainsString('first-live-secret', $encoded);
        $this->assertStringNotContainsString('payload_json', $encoded);
    }

    public function test_diagnostics_service_returns_recommendations_for_trace_gap(): void
    {
        $trace = app(MailReceptionTraceService::class)->readinessReview();
        $diagnostics = app(FirstLiveMailDiagnosticsService::class)->analyze([], $trace);

        $this->assertSame('warning', $diagnostics['status']);
        $this->assertSame('pending', $diagnostics['traceability']);
        $this->assertContains('First live mail trace is pending the first message.', $diagnostics['recommendations']);
    }

    public function test_queue_and_webhook_review_block_unsafe_configuration(): void
    {
        config([
            'queue.default' => 'sync',
            'mail-providers.activation.states.mailgun' => 'inactive',
        ]);

        $report = app(FirstLiveMailReadinessService::class)->report('mailgun', 'live-mail.test');

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('active_provider', array_column($report['sections']['webhook']['blockers'], 'name'));
        $this->assertContains('worker_backed_queue', array_column($report['sections']['queue']['blockers'], 'name'));
    }

    public function test_mailbox_visibility_review_keeps_isolation_and_expiry_rules(): void
    {
        $visible = $this->message('probe@live-mail.test', 'visible-49');
        $this->message('other@live-mail.test', 'isolated-49');
        $this->message('probe@live-mail.test', 'expired-49', [
            'status' => EmailMessageStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);

        $report = app(FirstLiveMailReadinessService::class)->report(
            provider: 'mailgun',
            domain: 'live-mail.test',
            mailbox: 'probe@live-mail.test',
        );

        $this->assertSame($visible->uuid, app(PublicInboxMessageService::class)->list('probe@live-mail.test')->first()['uuid']);
        $this->assertSame([], $report['sections']['inbox']['blockers']);
    }

    public function test_first_live_mail_status_command_outputs_safe_summary(): void
    {
        [$intake] = $this->flow();

        $this->artisan('mail:first-live-status --provider=mailgun --domain=live-mail.test --mailbox=probe@live-mail.test --intake='.$intake->uuid)
            ->expectsOutput('First live mail readiness: READY')
            ->expectsOutput('Blockers: 0')
            ->expectsOutput('Diagnostics: READY')
            ->expectsOutput('Trace readiness: READY')
            ->doesntExpectOutputToContain('first-live-secret')
            ->doesntExpectOutputToContain('raw-secret-header')
            ->doesntExpectOutputToContain('<script>')
            ->assertSuccessful();
    }

    public function test_first_live_mail_status_command_fails_when_trace_is_incomplete(): void
    {
        $this->artisan('mail:first-live-status --provider=mailgun --domain=live-mail.test --mailbox=missing@live-mail.test')
            ->expectsOutput('First live mail readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: trace.intake_accepted')
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

    private function flow(): array
    {
        $intake = InboundMailIntake::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'mailgun',
            'provider_message_id' => 'provider-live-49',
            'intake_key' => 'trace-provider-live-49',
            'signature_valid' => true,
            'signature_checked_at' => now(),
            'status' => InboundIntakeStatus::Processed,
            'headers_json' => ['x-secret-token' => 'raw-secret-header'],
            'payload_json' => ['provider_message_id' => 'provider-live-49', 'secret' => 'raw-secret-header'],
            'normalized_payload_json' => ['provider_id' => 'provider-live-49', 'mailbox_address' => 'probe@live-mail.test'],
            'queued_at' => now(),
            'processed_at' => now(),
        ]);
        $message = $this->message('probe@live-mail.test', 'provider-live-49', ['intake_key' => 'trace-provider-live-49']);

        return [$intake, $message];
    }

    private function message(string $mailbox, string $providerId, array $overrides = []): EmailMessage
    {
        [$local, $domain] = explode('@', $mailbox, 2);

        return EmailMessage::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'mailbox_address' => $mailbox,
            'recipient_local_part' => $local,
            'recipient_domain' => $domain,
            'from_email' => 'sender@example.net',
            'from_name' => 'Sender',
            'subject' => 'First live mail',
            'text_body' => 'Safe text body',
            'html_body' => '<script>unsafe()</script>',
            'sanitized_html_body' => '<p>Safe body</p>',
            'status' => EmailMessageStatus::Processed,
            'parse_status' => EmailParseStatus::Parsed,
            'intake_source' => 'provider',
            'provider_id' => $providerId,
            'is_quarantined' => false,
            'abuse_score' => 0,
            'retention_tier' => RetentionTier::Standard,
            'expires_at' => now()->addHour(),
            'received_at' => now(),
            'processed_at' => now(),
        ], $overrides));
    }
}
