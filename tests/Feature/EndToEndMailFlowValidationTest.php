<?php

namespace Tests\Feature;

use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\InboundIntakeStatus;
use App\Enums\RetentionTier;
use App\Jobs\ProcessInboundMailIntake;
use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Models\OperationsEvent;
use App\Services\Mail\AttachmentValidationService;
use App\Services\Mail\LoadReadinessService;
use App\Services\Mail\MimeValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EndToEndMailFlowValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'mailgun-secret',
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.postmark.signing_key' => 'postmark-secret',
            'mail-providers.providers.ses.enabled' => true,
            'mail-providers.providers.ses.signing_key' => 'ses-secret',
            'mail-providers.signature_tolerance_seconds' => 300,
            'domains.public_mailbox.default_domain' => 'example.test',
            'domains.public_mailbox.allowed_domains' => ['example.test'],
            'tempmail.public_inbox.mailbox_session_key' => 'public_inbox.mailbox',
        ]);
    }

    public function test_provider_specific_webhook_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('webhooks.mailgun'));
        $this->assertTrue(Route::has('webhooks.postmark'));
        $this->assertTrue(Route::has('webhooks.ses'));
    }

    public function test_disabled_provider_is_rejected_safely(): void
    {
        config(['mail-providers.providers.mailgun.enabled' => false]);

        $this->postJson('/webhooks/mailgun', $this->mailgunPayload())
            ->assertForbidden()
            ->assertJson(['ok' => false, 'status' => 'provider_disabled']);

        $this->assertDatabaseHas('operations_events', [
            'event_type' => 'webhook_rejected',
        ]);
        $this->assertSame(0, InboundMailIntake::query()->count());
    }

    public function test_mailgun_webhook_verification_queues_intake_and_records_observability(): void
    {
        Queue::fake();

        $this->postJson('/webhooks/mailgun', $this->mailgunPayload())
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'queued']);

        $intake = InboundMailIntake::query()->firstOrFail();

        $this->assertSame('mailgun', $intake->provider->value);
        $this->assertTrue($intake->signature_valid);
        $this->assertSame(InboundIntakeStatus::Queued, $intake->status);
        $this->assertProviderEvent('webhook_received');
        $this->assertProviderEvent('webhook_verified');
        $this->assertProviderEvent('webhook_processed');
        Queue::assertPushed(ProcessInboundMailIntake::class);
    }

    public function test_invalid_signature_and_malformed_payloads_are_rejected(): void
    {
        $bad = $this->mailgunPayload();
        $bad['signature'] = 'bad-signature';

        $this->postJson('/webhooks/mailgun', $bad)
            ->assertUnauthorized()
            ->assertJson(['ok' => false, 'status' => 'rejected']);

        $this->postJson('/webhooks/mailgun', [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'status' => 'malformed']);
    }

    public function test_replay_and_duplicate_protection_work(): void
    {
        Queue::fake();
        $payload = $this->mailgunPayload(['subject' => 'Replay ready']);

        $first = $this->postJson('/webhooks/mailgun', $payload)->assertOk()->json('uuid');
        $second = $this->postJson('/webhooks/mailgun', $payload)->assertOk()->assertJson(['status' => 'duplicate'])->json('uuid');

        $this->assertSame($first, $second);
        $this->assertSame(1, InboundMailIntake::query()->count());
        $this->assertProviderEvent('webhook_duplicate');
        Queue::assertPushed(ProcessInboundMailIntake::class, 1);
    }

    public function test_end_to_end_provider_to_inbox_flow_is_visible(): void
    {
        Queue::fake();

        $this->postJson('/webhooks/mailgun', $this->mailgunPayload([
            'recipient' => 'demo@example.test',
            'subject' => 'Inbox visible',
            'body-plain' => 'Hello inbox',
        ]))->assertOk();

        $intake = InboundMailIntake::query()->firstOrFail();

        (new ProcessInboundMailIntake($intake->id))->handle(
            app(\App\Services\Mail\InboundMailIntakeService::class),
            app(\App\Services\Mail\EmailMessageStorageService::class),
        );

        $message = EmailMessage::query()->firstOrFail();

        $this->assertSame('Inbox visible', $message->subject);
        $this->withSession(['public_inbox.mailbox' => 'demo@example.test'])
            ->getJson('/inbox/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.uuid', $message->uuid);

        $this->withSession(['public_inbox.mailbox' => 'demo@example.test'])
            ->getJson('/inbox/messages/'.$message->uuid)
            ->assertOk()
            ->assertJsonPath('message.text_body', 'Hello inbox')
            ->assertJsonMissingPath('message.html_body');
    }

    public function test_inbox_lifecycle_retention_and_quarantine_remain_safe(): void
    {
        $visible = $this->messageFor('demo@example.test', ['subject' => 'Visible']);
        $this->messageFor('demo@example.test', ['subject' => 'Expired', 'expires_at' => now()->subMinute()]);
        $this->messageFor('demo@example.test', ['subject' => 'Quarantined', 'status' => EmailMessageStatus::Quarantined]);

        $this->withSession(['public_inbox.mailbox' => 'demo@example.test'])
            ->getJson('/inbox/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.uuid', $visible->uuid)
            ->assertJsonMissing(['subject' => 'Expired'])
            ->assertJsonMissing(['subject' => 'Quarantined']);
    }

    public function test_mime_and_attachment_validation_work(): void
    {
        $this->assertTrue(app(MimeValidationService::class)->validatePayload($this->mailgunPayload()));

        $this->expectException(ValidationException::class);
        app(MimeValidationService::class)->validatePayload([
            'sender' => "bad@example.test\nBcc: attacker@example.test",
        ]);
    }

    public function test_attachment_validation_rejects_invalid_metadata(): void
    {
        $validator = app(AttachmentValidationService::class);

        $this->assertTrue($validator->validate([
            ['mime_type' => 'application/pdf', 'size' => 1024],
        ]));
        $this->assertTrue($validator->storageReady());

        $this->expectException(ValidationException::class);
        $validator->validate([
            ['mime_type' => 'not-a-mime', 'size' => 10],
        ]);
    }

    public function test_postmark_and_ses_webhooks_queue_when_enabled(): void
    {
        Queue::fake();

        $this->postJson('/webhooks/postmark', [
            'MessageID' => 'pm-step32-1',
            'OriginalRecipient' => 'demo@example.test',
            'From' => 'sender@example.test',
            'Subject' => 'Postmark queued',
        ], [
            'X-Postmark-Webhook-Token' => 'postmark-secret',
            'X-Postmark-Timestamp' => (string) time(),
        ])->assertOk()->assertJson(['status' => 'queued']);

        $timestamp = now()->toIso8601String();
        $this->postJson('/webhooks/ses', [
            'MessageId' => 'ses-step32-1',
            'Timestamp' => $timestamp,
            'Signature' => hash_hmac('sha256', 'ses-step32-1|'.$timestamp, 'ses-secret'),
            'mail' => [
                'messageId' => 'ses-step32-1',
                'source' => 'sender@example.test',
                'destination' => ['demo@example.test'],
            ],
        ])->assertOk()->assertJson(['status' => 'queued']);

        $this->assertSame(2, InboundMailIntake::query()->count());
        Queue::assertPushed(ProcessInboundMailIntake::class, 2);
    }

    public function test_load_readiness_service_reports_capacity_without_generating_load(): void
    {
        config([
            'inbound.queue.name' => 'inbound-mail',
            'mail-providers.throughput.queue_pending_warning' => 1,
            'mail-providers.throughput.intake_per_minute_warning' => 1,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'inbound-mail',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        InboundMailIntake::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'mailgun',
            'signature_valid' => true,
            'status' => InboundIntakeStatus::Queued,
        ]);

        $report = app(LoadReadinessService::class)->report();

        $this->assertSame('warning', $report['queue']['status']);
        $this->assertSame('warning', $report['intake']['status']);
        $this->assertSame(1, $report['providers']['mailgun']);
    }

    private function mailgunPayload(array $overrides = []): array
    {
        $timestamp = (string) time();
        $token = 'token-step32';

        return array_merge([
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'mailgun-secret'),
            'recipient' => 'demo@example.test',
            'sender' => 'sender@example.net',
            'subject' => 'Mailgun ready',
            'body-plain' => 'Plain body',
            'Message-Id' => 'mg-'.Str::uuid(),
        ], $overrides);
    }

    private function messageFor(string $mailbox, array $overrides = []): EmailMessage
    {
        [$local, $domain] = explode('@', $mailbox, 2);

        return EmailMessage::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'mailbox_address' => $mailbox,
            'recipient_local_part' => $local,
            'recipient_domain' => $domain,
            'from_email' => 'sender@example.com',
            'from_name' => 'Sender',
            'subject' => 'Hello',
            'text_body' => 'Plain body',
            'html_body' => '<script>unsafe()</script>',
            'sanitized_html_body' => '<p>Safe body</p>',
            'status' => EmailMessageStatus::Processed,
            'parse_status' => EmailParseStatus::Parsed,
            'is_quarantined' => false,
            'abuse_score' => 0,
            'retention_tier' => RetentionTier::Standard,
            'expires_at' => now()->addHour(),
            'received_at' => now(),
            'processed_at' => now(),
        ], $overrides));
    }

    private function assertProviderEvent(string $eventType): void
    {
        $this->assertTrue(
            OperationsEvent::query()->where('event_type', $eventType)->exists(),
            "Expected provider event {$eventType} was not recorded.",
        );
    }
}
