<?php

namespace Tests\Feature;

use App\Enums\EmailAttachmentScanStatus;
use App\Enums\EmailAttachmentStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\RetentionTier;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicInboxFoundationTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionKey = 'public_inbox.mailbox';

    private string $installerTestPath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'domains.public_mailbox.default_domain' => 'example.test',
            'domains.public_mailbox.allowed_domains' => ['example.test'],
            'tempmail.public_inbox.mailbox_session_key' => $this->sessionKey,
            'tempmail.public_inbox.mailbox_local_part_length' => 12,
        ]);

        $this->installerTestPath = storage_path('framework/testing/public-inbox-installer');
        File::deleteDirectory($this->installerTestPath);
        File::ensureDirectoryExists($this->installerTestPath);
        File::put($this->installerTestPath.'/.env', "APP_KEY=configured\n");
        File::put($this->installerTestPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerTestPath.'/.env',
            'installer.lock_path' => $this->installerTestPath.'/install.lock',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerTestPath);

        parent::tearDown();
    }

    public function test_inbox_loads(): void
    {
        $this->get('/inbox')
            ->assertOk()
            ->assertSee('Public Inbox')
            ->assertSee('Generate mailbox');
    }

    public function test_mailbox_can_be_generated_and_stored_in_session(): void
    {
        $this->post('/inbox/generate')
            ->assertRedirect(route('inbox.index'))
            ->assertSessionHas($this->sessionKey);

        $mailbox = session($this->sessionKey);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}@example\.test$/', $mailbox);
    }

    public function test_mailbox_can_be_rotated(): void
    {
        $this->withSession([$this->sessionKey => 'firstbox1234@example.test'])
            ->post('/inbox/rotate')
            ->assertRedirect(route('inbox.index'))
            ->assertSessionHas($this->sessionKey);

        $this->assertNotSame('firstbox1234@example.test', session($this->sessionKey));
    }

    public function test_mailbox_can_be_forgotten(): void
    {
        $this->withSession([$this->sessionKey => 'firstbox1234@example.test'])
            ->post('/inbox/forget')
            ->assertRedirect(route('inbox.index'))
            ->assertSessionMissing($this->sessionKey);
    }

    public function test_messages_endpoint_returns_only_current_mailbox_messages(): void
    {
        $visible = $this->messageFor('current@example.test', ['subject' => 'Visible']);
        $this->messageFor('other@example.test', ['subject' => 'Hidden']);

        $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.uuid', $visible->uuid)
            ->assertJsonMissing(['subject' => 'Hidden']);
    }

    public function test_messages_endpoint_hides_expired_messages(): void
    {
        $this->messageFor('current@example.test', [
            'subject' => 'Expired',
            'expires_at' => now()->subMinute(),
        ]);

        $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages')
            ->assertOk()
            ->assertJsonCount(0, 'messages');
    }

    public function test_messages_endpoint_hides_quarantined_messages(): void
    {
        $this->messageFor('current@example.test', [
            'subject' => 'Quarantined',
            'is_quarantined' => true,
        ]);

        $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages')
            ->assertOk()
            ->assertJsonCount(0, 'messages');
    }

    public function test_message_detail_blocks_other_mailbox_messages(): void
    {
        $message = $this->messageFor('other@example.test');

        $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages/'.$message->uuid)
            ->assertNotFound();
    }

    public function test_json_does_not_expose_internal_ids_or_storage_paths(): void
    {
        $message = $this->messageFor('current@example.test');
        EmailAttachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'email_message_id' => $message->id,
            'original_filename' => 'invoice.pdf',
            'safe_filename' => 'invoice.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1234,
            'checksum' => 'abc123',
            'storage_disk' => 'local',
            'storage_path' => 'private/mail/secret.pdf',
            'scan_status' => EmailAttachmentScanStatus::Clean,
            'status' => EmailAttachmentStatus::Stored,
        ]);

        $payload = $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages/'.$message->uuid)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('"id"', $payload);
        $this->assertStringNotContainsString('email_message_id', $payload);
        $this->assertStringNotContainsString('storage_path', $payload);
        $this->assertStringNotContainsString('private/mail/secret.pdf', $payload);
    }

    public function test_unsafe_html_body_is_not_returned_as_renderable_content(): void
    {
        $message = $this->messageFor('current@example.test', [
            'html_body' => '<script>alert("x")</script><img src="https://remote.test/pixel.png">',
            'sanitized_html_body' => null,
            'text_body' => 'Safe fallback',
        ]);

        $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages/'.$message->uuid)
            ->assertOk()
            ->assertJsonPath('message.uuid', $message->uuid)
            ->assertJsonPath('message.text_body', 'Safe fallback')
            ->assertJsonPath('message.sanitized_html_body', null)
            ->assertJsonMissingPath('message.html_body');
    }

    public function test_polling_route_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->withSession([$this->sessionKey => 'current@example.test'])
                ->getJson('/inbox/messages')
                ->assertOk();
        }

        $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages')
            ->assertTooManyRequests();
    }

    public function test_existing_routes_auth_admin_and_installer_still_behave(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/admin')->assertForbidden();
        $this->get('/install')->assertStatus(302);
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
            'html_body' => '<strong>Unsafe source</strong>',
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
}
