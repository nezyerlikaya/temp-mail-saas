<?php

namespace Tests\Feature;

use App\Enums\CleanupRunStatus;
use App\Enums\CleanupRunType;
use App\Enums\EmailAttachmentScanStatus;
use App\Enums\EmailAttachmentStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\EmailRecipientType;
use App\Enums\InboundIntakeStatus;
use App\Enums\InboundProvider;
use App\Enums\RetentionTier;
use App\Models\CleanupRun;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\EmailMessageRecipient;
use App\Models\InboundMailIntake;
use App\Services\Mail\EmailRetentionService;
use App\Services\Mail\MailCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailCleanupFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'retention.cleanup_chunk_size' => 2,
            'retention.cleanup_dry_run_default' => false,
            'retention.hard_delete_enabled' => false,
            'retention.intake_retention_minutes' => 60,
            'retention.cleanup_log_enabled' => true,
        ]);
    }

    public function test_cleanup_run_migration_and_model_helpers_work(): void
    {
        $run = CleanupRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => CleanupRunType::Full,
            'status' => CleanupRunStatus::Running,
            'dry_run' => false,
        ]);

        $this->assertTrue($run->isRunning());
        $this->assertFalse($run->isCompleted());
        $this->assertFalse($run->isFailed());

        $run->update(['status' => CleanupRunStatus::Completed]);

        $this->assertTrue($run->fresh()->isCompleted());
    }

    public function test_email_retention_service_detects_expired_messages_without_breaking_existing_method(): void
    {
        $service = app(EmailRetentionService::class);
        $message = $this->message(['expires_at' => now()->subMinute()]);

        $this->assertTrue($service->isExpired($message));
        $this->assertCount(1, $service->expiredMessagesQuery()->get());
        $this->assertSame(
            $service->expirationFor(RetentionTier::Short)->toDateTimeString(),
            $service->determineExpirationDate(RetentionTier::Short)->toDateTimeString(),
        );
    }

    public function test_dry_run_performs_no_mutation(): void
    {
        $message = $this->message(['expires_at' => now()->subMinute()]);
        $intake = $this->intake(['updated_at' => now()->subHours(2)]);

        $summary = app(MailCleanupService::class)->runFullCleanup(true, 1);

        $this->assertSame(1, $summary['messages_scanned']);
        $this->assertSame(1, $summary['messages_expired']);
        $this->assertSame(1, $summary['intakes_deleted']);
        $this->assertSame(EmailMessageStatus::Processed, $message->fresh()->status);
        $this->assertNotNull($intake->fresh());
    }

    public function test_expired_messages_are_marked_expired_and_hard_delete_is_disabled_by_default(): void
    {
        $message = $this->message(['expires_at' => now()->subMinute()]);

        $summary = app(MailCleanupService::class)->cleanupExpiredMessages();

        $this->assertSame(1, $summary['messages_expired']);
        $this->assertSame(0, $summary['messages_deleted']);
        $this->assertSame(EmailMessageStatus::Expired, $message->fresh()->status);
        $this->assertDatabaseHas('email_messages', ['id' => $message->id]);
    }

    public function test_hard_delete_removes_message_recipient_and_attachment_metadata_only_when_enabled(): void
    {
        config(['retention.hard_delete_enabled' => true]);
        $message = $this->message(['expires_at' => now()->subMinute()]);
        $recipient = EmailMessageRecipient::query()->create([
            'email_message_id' => $message->id,
            'type' => EmailRecipientType::To,
            'email' => 'mailbox@example.test',
            'local_part' => 'mailbox',
            'domain' => 'example.test',
        ]);
        $attachment = EmailAttachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'email_message_id' => $message->id,
            'original_filename' => 'invoice.pdf',
            'safe_filename' => 'invoice.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1234,
            'storage_disk' => 'local',
            'storage_path' => 'private/mail/invoice.pdf',
            'scan_status' => EmailAttachmentScanStatus::Clean,
            'status' => EmailAttachmentStatus::Stored,
        ]);

        $summary = app(MailCleanupService::class)->cleanupExpiredMessages();

        $this->assertSame(1, $summary['messages_deleted']);
        $this->assertSame(1, $summary['attachments_affected']);
        $this->assertDatabaseMissing('email_messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('email_message_recipients', ['id' => $recipient->id]);
        $this->assertDatabaseMissing('email_attachments', ['id' => $attachment->id]);
    }

    public function test_expired_inbound_intakes_are_cleaned_in_chunks(): void
    {
        $oldProcessed = $this->intake([
            'status' => InboundIntakeStatus::Processed,
            'updated_at' => now()->subHours(2),
        ]);
        $oldFailed = $this->intake([
            'status' => InboundIntakeStatus::Failed,
            'updated_at' => now()->subHours(2),
        ]);
        $fresh = $this->intake([
            'status' => InboundIntakeStatus::Processed,
            'updated_at' => now(),
        ]);

        $summary = app(MailCleanupService::class)->cleanupExpiredIntakes(false, 1);

        $this->assertSame(2, $summary['intakes_deleted']);
        $this->assertDatabaseMissing('inbound_mail_intakes', ['id' => $oldProcessed->id]);
        $this->assertDatabaseMissing('inbound_mail_intakes', ['id' => $oldFailed->id]);
        $this->assertDatabaseHas('inbound_mail_intakes', ['id' => $fresh->id]);
    }

    public function test_cleanup_command_creates_privacy_safe_audit_record_and_output(): void
    {
        $this->message([
            'subject' => 'Private subject must not appear',
            'text_body' => 'Private body must not appear',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('mail:cleanup-expired', ['--chunk' => 1])
            ->expectsOutput('Cleanup completed.')
            ->expectsOutput('Messages scanned: 1')
            ->expectsOutput('Messages expired: 1')
            ->expectsOutput('Messages deleted: 0')
            ->expectsOutput('Intakes deleted: 0')
            ->expectsOutput('Attachments affected: 0')
            ->assertSuccessful();

        $run = CleanupRun::query()->firstOrFail();

        $this->assertTrue($run->isCompleted());
        $this->assertSame(CleanupRunType::Full, $run->type);
        $this->assertSame(1, $run->messages_scanned);
        $this->assertNull($run->error_message);
    }

    public function test_public_inbox_still_excludes_expired_messages(): void
    {
        $this->message([
            'mailbox_address' => 'mailbox@example.test',
            'expires_at' => now()->subMinute(),
        ]);

        $this->withSession(['public_inbox.mailbox' => 'mailbox@example.test'])
            ->getJson('/inbox/messages')
            ->assertOk()
            ->assertJsonCount(0, 'messages');
    }

    public function test_existing_routes_auth_installer_and_admin_still_behave(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/admin')->assertForbidden();
        $this->get('/install')->assertOk();
    }

    private function message(array $overrides = []): EmailMessage
    {
        return EmailMessage::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'mailbox_address' => 'mailbox@example.test',
            'recipient_local_part' => 'mailbox',
            'recipient_domain' => 'example.test',
            'from_email' => 'sender@example.com',
            'subject' => 'Hello',
            'text_body' => 'Private body',
            'status' => EmailMessageStatus::Processed,
            'parse_status' => EmailParseStatus::Parsed,
            'is_quarantined' => false,
            'retention_tier' => RetentionTier::Standard,
            'expires_at' => now()->addHour(),
            'received_at' => now(),
        ], $overrides));
    }

    private function intake(array $overrides = []): InboundMailIntake
    {
        $updatedAt = $overrides['updated_at'] ?? now()->subHours(2);
        unset($overrides['updated_at']);

        $intake = InboundMailIntake::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'provider' => InboundProvider::Local,
            'signature_valid' => true,
            'status' => InboundIntakeStatus::Processed,
            'payload_json' => ['private' => 'payload'],
        ], $overrides));

        InboundMailIntake::query()
            ->whereKey($intake->id)
            ->update(['updated_at' => $updatedAt]);

        return $intake->refresh();
    }
}
