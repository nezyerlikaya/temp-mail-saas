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
use App\Services\Mail\FirstRealMailValidationService;
use App\Services\Mail\MailReceptionTraceService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class FirstRealMailValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->installerPath = storage_path('framework/testing/first-real-mail');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('f', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.key' => 'base64:'.base64_encode(str_repeat('f', 32)),
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.onboarding.dns_readiness.mx' => true,
            'domains.onboarding.dns_readiness.spf' => true,
            'domains.onboarding.dns_readiness.dkim' => true,
            'domains.onboarding.dns_readiness.dmarc' => true,
            'domains.onboarding.dns_readiness.provider_mapping' => true,
            'domains.public_mailbox.default_domain' => 'real-mail.test',
            'domains.public_mailbox.allowed_domains' => ['real-mail.test'],
            'features-gates.plans.free.allowed_domains' => ['real-mail.test'],
            'mail-providers.activation.safety.require_staging_passed' => false,
            'mail-providers.activation.safety.require_webhook_ready' => false,
            'mail-providers.activation.safety.require_queue_ready' => false,
            'mail-providers.activation.safety.require_installer_ready' => false,
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.activation.readiness.providers' => ['mailgun'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'first-real-secret',
            'inbound.queue.name' => 'inbound-mail',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_first_real_mail_validation_service_reports_ready(): void
    {
        $this->domain();

        $report = app(FirstRealMailValidationService::class)->report('mailgun', 'real-mail.test', 'probe@real-mail.test');

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertContains('provider_active_state', array_column($report['checks'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'first_mail_validation_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'first_mail_validation_ready']);
    }

    public function test_provider_active_state_is_required(): void
    {
        config(['mail-providers.activation.states.mailgun' => 'ready']);
        $this->domain();

        $report = app(FirstRealMailValidationService::class)->report('mailgun', 'real-mail.test');

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('provider_active_state', array_column($report['blockers'], 'name'));
    }

    public function test_domain_readiness_is_required(): void
    {
        $this->domain(onboardingState: DomainOnboardingState::Ready);

        $report = app(FirstRealMailValidationService::class)->report('mailgun', 'real-mail.test');

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('domain_onboarding_active', array_column($report['blockers'], 'name'));
    }

    public function test_mail_trace_service_by_intake_uuid_is_safe(): void
    {
        [$intake, $message] = $this->flow();

        $trace = app(MailReceptionTraceService::class)->byIntakeUuid($intake->uuid);

        $this->assertSame('complete', $trace['status']);
        $this->assertSame($intake->uuid, $trace['intake']['uuid']);
        $this->assertSame($message->uuid, $trace['message']['uuid']);
        $this->assertTrue($trace['lifecycle']['public_inbox_visibility']);
        $this->assertArrayNotHasKey('payload_json', $trace['intake']);
        $this->assertArrayNotHasKey('html_body', $trace['message']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'first_mail_trace_completed']);
    }

    public function test_mail_trace_service_by_message_uuid_and_mailbox(): void
    {
        [, $message] = $this->flow();

        $byMessage = app(MailReceptionTraceService::class)->byMessageUuid($message->uuid);
        $byMailbox = app(MailReceptionTraceService::class)->byMailbox($message->mailbox_address);

        $this->assertSame('partial', $byMessage['status']);
        $this->assertSame($message->uuid, $byMessage['message']['uuid']);
        $this->assertSame($message->uuid, $byMailbox['message']['uuid']);
    }

    public function test_mail_trace_service_by_provider_message_id(): void
    {
        [$intake, $message] = $this->flow(providerId: 'provider-real-41');

        $trace = app(MailReceptionTraceService::class)->byProviderMessageId('provider-real-41');

        $this->assertSame($intake->uuid, $trace['intake']['uuid']);
        $this->assertSame($message->uuid, $trace['message']['uuid']);
    }

    public function test_command_output_is_safe_and_can_trace(): void
    {
        $this->domain();
        [$intake] = $this->flow();

        $this->artisan('mail:first-real-check --provider=mailgun --domain=real-mail.test --mailbox=probe@real-mail.test --intake='.$intake->uuid)
            ->expectsOutput('First real mail validation: READY')
            ->expectsOutput('Trace status: COMPLETE')
            ->doesntExpectOutputToContain('first-real-secret')
            ->doesntExpectOutputToContain('raw-secret-header')
            ->doesntExpectOutputToContain('<script>')
            ->assertSuccessful();
    }

    public function test_no_raw_payload_or_secret_leaks_from_trace(): void
    {
        [$intake] = $this->flow(payload: [
            'provider_message_id' => 'provider-secret-41',
            'raw_secret_header' => 'raw-secret-header',
            'html_body' => '<script>unsafe()</script>',
        ]);

        $encoded = json_encode(app(MailReceptionTraceService::class)->byIntakeUuid($intake->uuid), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('raw-secret-header', $encoded);
        $this->assertStringNotContainsString('<script>', $encoded);
        $this->assertStringNotContainsString('payload_json', $encoded);
        $this->assertStringNotContainsString('headers_json', $encoded);
    }

    public function test_mailbox_visibility_validation_hides_expired_and_quarantined_messages(): void
    {
        $visible = $this->message('probe@real-mail.test', providerId: 'visible-41');
        $this->message('probe@real-mail.test', providerId: 'expired-41', overrides: [
            'status' => EmailMessageStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
        $this->message('probe@real-mail.test', providerId: 'quarantined-41', overrides: [
            'status' => EmailMessageStatus::Quarantined,
            'is_quarantined' => true,
        ]);

        $trace = app(MailReceptionTraceService::class)->byMailbox('probe@real-mail.test');

        $this->assertSame($visible->uuid, $trace['message']['uuid']);
        $this->assertTrue($trace['message']['visible_in_public_inbox']);
    }

    public function test_duplicate_and_replay_diagnostics_remain_safe(): void
    {
        [$intake] = $this->flow(providerId: 'duplicate-41');
        $duplicate = InboundMailIntake::query()->where('provider_message_id', 'duplicate-41')->firstOrFail();

        $this->assertSame($intake->id, $duplicate->id);
        $this->assertSame(1, InboundMailIntake::query()->where('provider_message_id', 'duplicate-41')->count());
        $this->assertTrue(app(MailReceptionTraceService::class)->byProviderMessageId('duplicate-41')['lifecycle']['queued_job']);
    }

    private function domain(DomainOnboardingState $onboardingState = DomainOnboardingState::Active): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => 'real-mail.test',
            'status' => $onboardingState === DomainOnboardingState::Active ? DomainStatus::Active : DomainStatus::Inactive,
            'onboarding_state' => $onboardingState,
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

    private function flow(string $providerId = 'provider-real-41', array $payload = []): array
    {
        $intake = InboundMailIntake::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'mailgun',
            'provider_message_id' => $providerId,
            'intake_key' => 'trace-'.$providerId,
            'signature_valid' => true,
            'signature_checked_at' => now(),
            'status' => InboundIntakeStatus::Processed,
            'headers_json' => ['x-safe' => 'present', 'x-secret-token' => 'raw-secret-header'],
            'payload_json' => array_merge(['provider_message_id' => $providerId], $payload),
            'normalized_payload_json' => ['provider_id' => $providerId, 'mailbox_address' => 'probe@real-mail.test'],
            'queued_at' => now(),
            'processed_at' => now(),
        ]);
        $message = $this->message('probe@real-mail.test', $providerId, ['intake_key' => 'trace-'.$providerId]);

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
            'subject' => 'First real trace',
            'text_body' => 'Safe text body',
            'html_body' => '<script>unsafe()</script>',
            'sanitized_html_body' => '<p>Safe HTML body</p>',
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
