<?php

namespace Tests\Feature;

use App\Enums\EmailAttachmentScanStatus;
use App\Enums\EmailAttachmentStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\EmailRecipientType;
use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Enums\RetentionTier;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\Media;
use App\Services\Mail\EmailMessageStorageService;
use App\Services\Mail\EmailRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailMessageStorageFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_storage_migrations_work(): void
    {
        $this->assertTrue(Schema::hasTable('email_messages'));
        $this->assertTrue(Schema::hasTable('email_message_recipients'));
        $this->assertTrue(Schema::hasTable('email_attachments'));

        $this->assertTrue(Schema::hasColumns('email_messages', [
            'uuid',
            'mailbox_address',
            'recipient_local_part',
            'recipient_domain',
            'from_email',
            'from_name',
            'subject',
            'message_id_header',
            'text_body',
            'html_body',
            'sanitized_html_body',
            'status',
            'parse_status',
            'retention_tier',
            'expires_at',
            'received_at',
            'processed_at',
            'failed_at',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('email_message_recipients', [
            'email_message_id',
            'type',
            'email',
            'name',
            'local_part',
            'domain',
        ]));

        $this->assertTrue(Schema::hasColumns('email_attachments', [
            'uuid',
            'email_message_id',
            'media_id',
            'original_filename',
            'safe_filename',
            'mime_type',
            'size',
            'checksum',
            'storage_disk',
            'storage_path',
            'scan_status',
            'status',
        ]));
    }

    public function test_storage_service_creates_message_with_recipients_and_attachment_metadata_only(): void
    {
        $message = app(EmailMessageStorageService::class)->create([
            'mailbox_address' => 'demo@example.com',
            'from_email' => 'sender@example.net',
            'from_name' => 'Sender',
            'subject' => 'Hello',
            'text_body' => 'Plain text body',
            'recipients' => [
                [
                    'type' => 'to',
                    'email' => 'demo@example.com',
                    'name' => 'Demo',
                ],
                [
                    'type' => 'cc',
                    'email' => 'copy@example.com',
                ],
            ],
            'attachments' => [
                [
                    'original_filename' => 'Invoice May.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 1200,
                    'checksum' => 'abc123',
                ],
            ],
        ]);

        $this->assertTrue(Str::isUuid($message->uuid));
        $this->assertSame('demo', $message->recipient_local_part);
        $this->assertSame('example.com', $message->recipient_domain);
        $this->assertSame(EmailMessageStatus::Received, $message->status);
        $this->assertSame(EmailParseStatus::Pending, $message->parse_status);
        $this->assertSame(RetentionTier::Standard, $message->retention_tier);
        $this->assertNotNull($message->expires_at);
        $this->assertCount(2, $message->recipients);
        $this->assertSame(EmailRecipientType::To, $message->recipients->first()->type);
        $this->assertSame('demo', $message->recipients->first()->local_part);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('invoice-may.pdf', $message->attachments->first()->safe_filename);
        $this->assertNull($message->attachments->first()->storage_path);
    }

    public function test_message_relationships_and_enum_casts_work(): void
    {
        $message = app(EmailMessageStorageService::class)->create([
            'mailbox_address' => 'demo@example.com',
            'status' => EmailMessageStatus::Queued,
            'parse_status' => EmailParseStatus::Parsing,
            'is_quarantined' => true,
            'quarantine_reason' => 'policy',
            'recipients' => [
                ['type' => 'bcc', 'email' => 'hidden@example.com'],
            ],
            'attachments' => [
                [
                    'original_filename' => 'scan.txt',
                    'scan_status' => EmailAttachmentScanStatus::Suspicious,
                    'status' => EmailAttachmentStatus::Pending,
                ],
            ],
        ]);

        $this->assertSame(EmailMessageStatus::Queued, $message->status);
        $this->assertSame(EmailParseStatus::Parsing, $message->parse_status);
        $this->assertTrue($message->isQuarantined());
        $this->assertFalse($message->isProcessed());
        $this->assertTrue($message->recipients()->first()->message->is($message));
        $this->assertTrue($message->attachments()->first()->message->is($message));
        $this->assertTrue($message->attachments()->first()->isSuspicious());

        $message->markProcessed();
        $this->assertTrue($message->fresh()->isProcessed());

        $message->markFailed();
        $this->assertSame(EmailMessageStatus::Failed, $message->fresh()->status);
    }

    public function test_attachment_relationship_to_media_works_and_is_nullable(): void
    {
        $message = app(EmailMessageStorageService::class)->create([
            'mailbox_address' => 'demo@example.com',
        ]);

        $media = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'local',
            'directory' => 'attachments/2026/05',
            'filename' => 'file.txt',
            'original_filename' => 'file.txt',
            'extension' => 'txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'visibility' => MediaVisibility::Private,
            'status' => MediaStatus::Active,
            'storage_driver' => 'local',
            'storage_path' => 'attachments/2026/05/file.txt',
        ]);

        $withoutMedia = $message->attachments()->create([
            'uuid' => (string) Str::uuid(),
            'scan_status' => EmailAttachmentScanStatus::Skipped,
            'status' => EmailAttachmentStatus::Pending,
        ]);

        $withMedia = $message->attachments()->create([
            'uuid' => (string) Str::uuid(),
            'media_id' => $media->id,
            'scan_status' => EmailAttachmentScanStatus::Clean,
            'status' => EmailAttachmentStatus::Stored,
        ]);

        $this->assertNull($withoutMedia->media);
        $this->assertTrue($withMedia->media->is($media));
        $this->assertTrue($withMedia->isStored());
        $this->assertTrue($withMedia->isClean());
    }

    public function test_retention_expiration_calculation_and_expired_query_work(): void
    {
        $retention = app(EmailRetentionService::class);
        $base = now();

        $this->assertTrue($retention->expirationFor(RetentionTier::Short, $base)->greaterThan($base));

        $expired = app(EmailMessageStorageService::class)->create([
            'mailbox_address' => 'old@example.com',
            'expires_at' => now()->subMinute(),
        ]);

        app(EmailMessageStorageService::class)->create([
            'mailbox_address' => 'fresh@example.com',
            'expires_at' => now()->addMinute(),
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertSame(1, $retention->expiredMessagesQuery()->count());
    }

    public function test_cleanup_command_marks_expired_records_safely(): void
    {
        $message = app(EmailMessageStorageService::class)->create([
            'mailbox_address' => 'old@example.com',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('mail:cleanup-expired')
            ->expectsOutput('Expired email messages processed: 1')
            ->expectsOutput('Action: marked expired.')
            ->assertExitCode(0);

        $this->assertSame(EmailMessageStatus::Expired, $message->fresh()->status);
        $this->assertDatabaseHas('email_messages', [
            'id' => $message->id,
            'deleted_at' => null,
        ]);
    }

    public function test_existing_routes_auth_and_admin_remain_intact(): void
    {
        $this->get('/')->assertOk();
        $this->getJson('/health')->assertOk();
        $this->get('/status')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/admin')->assertForbidden();
    }
}
