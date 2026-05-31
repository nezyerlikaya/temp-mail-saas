<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\BillingWebhookStatus;
use App\Enums\EmailAttachmentScanStatus;
use App\Enums\EmailAttachmentStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\IntegrationStatus;
use App\Enums\LanguageDirection;
use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Enums\RetentionTier;
use App\Enums\StaffStatus;
use App\Enums\UserIntegrationStatus;
use App\Enums\UserStatus;
use App\Models\ApiKey;
use App\Models\BillingWebhookEvent;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\Integration;
use App\Models\Language;
use App\Models\Media;
use App\Models\OutboundWebhook;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffUser;
use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Api\ApiKeyService;
use App\Services\Integrations\OutboundWebhookService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.providers.local.webhook_secret' => 'security-secret',
            'domains.public_mailbox.default_domain' => 'example.test',
            'domains.public_mailbox.allowed_domains' => ['example.test'],
        ]);
    }

    public function test_security_headers_are_applied_to_web_responses(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_admin_permission_bypass_attempts_are_rejected_server_side(): void
    {
        $staff = $this->staffWithPermissions(['localization.view']);

        $this->actingAs($staff, 'staff')
            ->put('/admin/localization/translations', [
                'translations' => [1 => 'bypass'],
            ])
            ->assertForbidden();
    }

    public function test_csrf_exceptions_are_limited_to_billing_webhooks(): void
    {
        $bootstrap = File::get(base_path('bootstrap/app.php'));

        $this->assertStringContainsString("'billing/webhooks/*'", $bootstrap);
        $this->assertStringNotContainsString("'admin/*'", $bootstrap);
        $this->assertStringNotContainsString("'install/*'", $bootstrap);
        $this->assertStringNotContainsString("'locale'", $bootstrap);

        $payload = $this->billingPayload(null);

        $this->withMiddleware()
            ->withHeader('X-Billing-Signature', $this->billingSignature($payload))
            ->postJson('/billing/webhooks/local', $payload)
            ->assertOk();
    }

    public function test_api_and_webhook_secrets_are_hidden_from_default_serialization(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $api = app(ApiKeyService::class)->create($user, 'Security API');
        $webhook = app(OutboundWebhookService::class)->createWebhook('https://example.com/hook', ['mail.received'], 'plain-webhook-secret');
        $integration = Integration::query()->create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'secure-local',
            'name' => 'Secure Local',
            'status' => IntegrationStatus::Active,
            'metadata' => [],
        ]);
        $userIntegration = UserIntegration::query()->create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'status' => UserIntegrationStatus::Connected,
            'configuration' => ['token' => 'encrypted-secret'],
            'connected_at' => now(),
        ]);

        $this->assertStringNotContainsString($api['api_key']->key_hash, $api['api_key']->toJson());
        $this->assertStringNotContainsString($webhook->secret_hash, $webhook->toJson());
        $this->assertStringNotContainsString('encrypted-secret', $userIntegration->fresh()->toJson());
    }

    public function test_storage_paths_hashes_and_raw_html_are_hidden_from_default_serialization(): void
    {
        $message = $this->messageFor('current@example.test');
        $attachment = EmailAttachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'email_message_id' => $message->id,
            'original_filename' => 'invoice.pdf',
            'safe_filename' => 'invoice.pdf',
            'mime_type' => 'application/pdf',
            'size' => 123,
            'checksum' => 'attachment-checksum',
            'storage_disk' => 'local',
            'storage_path' => 'private/mail/invoice.pdf',
            'scan_status' => EmailAttachmentScanStatus::Clean,
            'status' => EmailAttachmentStatus::Stored,
        ]);
        $media = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'local',
            'directory' => 'avatars/2026/05',
            'filename' => 'avatar.png',
            'original_filename' => 'avatar.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 100,
            'checksum' => 'media-checksum',
            'visibility' => MediaVisibility::Private,
            'status' => MediaStatus::Active,
            'storage_driver' => 'local',
            'storage_path' => 'private/media/avatar.png',
        ]);

        $this->assertStringNotContainsString('<script>unsafe()</script>', $message->toJson());
        $this->assertStringNotContainsString('private/mail/invoice.pdf', $attachment->toJson());
        $this->assertStringNotContainsString('attachment-checksum', $attachment->toJson());
        $this->assertStringNotContainsString('private/media/avatar.png', $media->toJson());
        $this->assertStringNotContainsString('media-checksum', $media->toJson());
    }

    public function test_billing_webhook_replay_without_event_id_is_idempotent(): void
    {
        $this->seed(PlanSeeder::class);
        $payload = $this->billingPayload(null);

        $this->withHeader('X-Billing-Signature', $this->billingSignature($payload))
            ->postJson('/billing/webhooks/local', $payload)
            ->assertOk()
            ->assertJson(['status' => 'processed']);

        $this->withHeader('X-Billing-Signature', $this->billingSignature($payload))
            ->postJson('/billing/webhooks/local', $payload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, BillingWebhookEvent::query()->count());
        $this->assertSame(BillingWebhookStatus::Processed, BillingWebhookEvent::query()->firstOrFail()->status);
    }

    public function test_public_rendering_escapes_localization_values(): void
    {
        $staff = $this->staffWithPermissions(['localization.view']);

        Language::query()->create([
            'code' => 'xx',
            'name' => '<script>alert(1)</script>',
            'native_name' => '<script>alert(2)</script>',
            'direction' => LanguageDirection::Ltr,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 1,
        ]);

        $this->actingAs($staff, 'staff')
            ->get('/admin/localization/languages')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_user_mass_assignment_cannot_escalate_status_plan_or_api_access(): void
    {
        $user = User::query()->create([
            'name' => 'Mass Assignment',
            'email' => 'mass@example.test',
            'password' => Hash::make('password'),
            'status' => UserStatus::Suspended,
            'account_tier' => AccountTier::Premium,
            'api_access_enabled' => true,
        ]);

        $user->refresh();

        $this->assertNotSame(UserStatus::Suspended, $user->status);
        $this->assertNotSame(AccountTier::Premium, $user->account_tier);
        $this->assertFalse((bool) $user->api_access_enabled);
    }

    public function test_api_key_hash_is_not_exposed_in_api_responses_or_usage_logs(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $created = app(ApiKeyService::class)->create($user, 'No leak');
        $hash = ApiKey::query()->findOrFail($created['api_key']->id)->key_hash;

        $response = $this->withHeader('Authorization', 'Bearer '.$created['plain_text_key'])
            ->getJson('/api/v1/ping')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($created['plain_text_key'], $response);
        $this->assertStringNotContainsString($hash, $response);
        $this->assertDatabaseMissing('api_usage_logs', [
            'endpoint' => $created['plain_text_key'],
        ]);
    }

    private function billingPayload(?string $eventId = 'evt_security_1'): array
    {
        $payload = [
            'type' => 'customer.subscription.updated',
            'customer' => [
                'id' => 'cus_security_1',
                'email' => 'billing@example.test',
            ],
            'subscription' => [
                'id' => 'sub_security_1',
                'plan' => 'local_premium',
                'status' => 'active',
            ],
        ];

        if ($eventId !== null) {
            $payload['id'] = $eventId;
        }

        return $payload;
    }

    private function billingSignature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), 'security-secret');
    }

    private function messageFor(string $mailbox): EmailMessage
    {
        [$local, $domain] = explode('@', $mailbox, 2);

        return EmailMessage::query()->create([
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
        ]);
    }

    private function staffWithPermissions(array $permissions): StaffUser
    {
        $staff = StaffUser::query()->create([
            'name' => 'Security Staff',
            'email' => uniqid('security-staff-', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Security Role',
            'slug' => uniqid('security-role-', false),
            'is_system' => false,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'group' => str($slug)->before('.')->toString(),
                ],
            );

            $role->permissions()->attach($permission);
        }

        $staff->roles()->attach($role);

        return $staff;
    }
}
