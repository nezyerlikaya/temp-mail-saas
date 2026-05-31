<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\AutomationActionType;
use App\Enums\AutomationRuleStatus;
use App\Enums\AutomationTriggerType;
use App\Enums\BillingWebhookStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\LanguageDirection;
use App\Enums\RetentionTier;
use App\Enums\StaffStatus;
use App\Models\AutomationRule;
use App\Models\BillingWebhookEvent;
use App\Models\EmailMessage;
use App\Models\Language;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffUser;
use App\Models\Translation;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Automation\AutomationEngine;
use App\Services\Automation\RuleEvaluator;
use App\Services\System\InstallationService;
use App\Services\System\InstallerLockService;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReleaseReadinessAuditTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionKey = 'public_inbox.mailbox';

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerPath = storage_path('framework/testing/release-readiness-installer');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)),
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'domains.public_mailbox.default_domain' => 'example.test',
            'domains.public_mailbox.allowed_domains' => ['example.test'],
            'tempmail.public_inbox.mailbox_session_key' => $this->sessionKey,
            'billing.providers.local.webhook_secret' => 'release-secret',
        ]);

        File::put($this->installerPath.'/.env', "APP_KEY=configured\n");
        File::put($this->installerPath.'/install.lock', '{}');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_installer_recovery_and_finish_redirect_are_release_ready(): void
    {
        File::delete($this->installerPath.'/.env');

        $status = app(InstallationService::class)->status();

        $this->assertTrue($status['recovery']);
        $this->assertTrue($status['installer_accessible']);

        $this->get('/install')->assertOk()->assertSee('Installation');
        $this->post('/install/finish')->assertRedirect(route('admin.login'));
        $this->get('/admin/login')->assertRedirect(route('login'));

        $this->assertFileExists($this->installerPath.'/.env');
        $this->assertFileExists($this->installerPath.'/install.lock');
        $this->assertStringContainsString('APP_KEY=', File::get($this->installerPath.'/.env'));
    }

    public function test_installer_lock_blocks_reinstall_when_healthy(): void
    {
        app(InstallerLockService::class)->create();

        $this->get('/install')->assertRedirect(route('home'));
    }

    public function test_admin_and_localization_routes_have_server_side_permission_coverage(): void
    {
        $adminRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'admin.')
                && $route->getName() !== 'admin.login')
            ->values();

        $this->assertNotEmpty($adminRoutes);

        foreach ($adminRoutes as $route) {
            $this->assertContains('staff.active', $route->gatherMiddleware(), $route->getName().' missing staff.active');
        }

        $this->actingAs($this->staffWithPermissions([]), 'staff')
            ->get('/admin/localization')
            ->assertForbidden();
    }

    public function test_mailbox_isolation_and_hidden_message_states_are_release_ready(): void
    {
        $visible = $this->messageFor('current@example.test', ['subject' => 'Visible']);
        $deleted = $this->messageFor('current@example.test', [
            'subject' => 'Deleted',
            'status' => EmailMessageStatus::Deleted,
        ]);
        $quarantined = $this->messageFor('current@example.test', [
            'subject' => 'Quarantined',
            'status' => EmailMessageStatus::Quarantined,
        ]);
        $other = $this->messageFor('other@example.test', ['subject' => 'Other']);

        $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.uuid', $visible->uuid)
            ->assertJsonMissing(['uuid' => $deleted->uuid])
            ->assertJsonMissing(['uuid' => $quarantined->uuid])
            ->assertJsonMissing(['uuid' => $other->uuid]);

        $this->withSession([$this->sessionKey => 'current@example.test'])
            ->getJson('/inbox/messages/'.$other->uuid)
            ->assertNotFound();
    }

    public function test_api_key_lifecycle_enforces_hashing_revocation_expiration_and_rate_limit(): void
    {
        config(['features-gates.plans.premium.api_rate_limit_per_minute' => 2]);

        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $service = app(ApiKeyService::class);
        $created = $service->create($user, 'Release key', metadata: [
            'safe' => 'value',
            'api_secret' => 'hidden',
        ]);

        $this->assertDatabaseMissing('api_keys', ['key_hash' => $created['plain_text_key']]);
        $this->assertSame(['safe' => 'value'], $created['api_key']->metadata);

        $this->withHeader('Authorization', 'Bearer '.$created['plain_text_key'])->getJson('/api/v1/ping')->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$created['plain_text_key'])->getJson('/api/v1/ping')->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$created['plain_text_key'])->getJson('/api/v1/ping')->assertTooManyRequests();

        $service->revoke($created['api_key']);

        $this->assertNull($service->verify($created['plain_text_key']));

        $expired = $service->create($user, 'Expired key', now()->subMinute());

        $this->assertNull($service->verify($expired['plain_text_key']));
    }

    public function test_billing_webhook_duplicate_with_bad_signature_does_not_downgrade_processed_event(): void
    {
        $this->seed(PlanSeeder::class);

        $payload = $this->billingPayload();

        $this->withHeader('X-Billing-Signature', $this->billingSignature($payload))
            ->postJson('/billing/webhooks/local', $payload)
            ->assertOk()
            ->assertJson(['status' => 'processed']);

        $this->withHeader('X-Billing-Signature', 'bad-signature')
            ->postJson('/billing/webhooks/local', $payload)
            ->assertUnauthorized()
            ->assertJson(['status' => 'rejected']);

        $event = BillingWebhookEvent::query()->firstOrFail();

        $this->assertSame(1, BillingWebhookEvent::query()->count());
        $this->assertSame(BillingWebhookStatus::Processed, $event->status);
        $this->assertFalse(Schema::hasColumn('billing_webhook_events', 'payload'));
    }

    public function test_localization_safeguards_reject_invalid_imports_and_preserve_default_language(): void
    {
        $this->seed(LanguageSeeder::class);

        $staff = $this->staffWithPermissions([
            'localization.view',
            'localization.manage',
            'localization.import',
        ]);
        $default = Language::query()->where('is_default', true)->firstOrFail();
        $language = Language::query()->create([
            'code' => 'ar',
            'name' => 'Arabic',
            'native_name' => 'Arabic',
            'direction' => LanguageDirection::Rtl,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 30,
        ]);

        $this->actingAs($staff, 'staff')
            ->delete('/admin/localization/languages/'.$default->id)
            ->assertSessionHasErrors('language');

        $this->actingAs($staff, 'staff')
            ->post('/admin/localization/import', [
                'language_id' => $language->id,
                'json' => '{"app":',
            ])->assertSessionHasErrors('json');

        $this->assertSame(0, Translation::query()->count());
        $this->withSession(['locale' => 'missing'])->get('/')->assertOk();
    }

    public function test_automation_conditions_are_deterministic_and_do_not_execute_code(): void
    {
        $evaluator = app(RuleEvaluator::class);

        $this->assertFalse($evaluator->matches([
            'field' => 'metadata.source; phpinfo()',
            'operator' => 'exists',
        ], ['metadata' => ['source' => 'test']]));

        AutomationRule::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Release safe rule',
            'trigger_type' => AutomationTriggerType::ScheduledEvent,
            'condition_group' => [
                'all' => [
                    ['field' => 'scheduled', 'operator' => 'equals', 'value' => true],
                ],
            ],
            'action_type' => AutomationActionType::Log,
            'priority' => 10,
            'status' => AutomationRuleStatus::Active,
            'metadata' => [],
        ]);

        $executions = app(AutomationEngine::class)->evaluate(AutomationTriggerType::ScheduledEvent, [
            'scheduled' => true,
            'raw_payload' => 'private',
        ], 'release-test');

        $this->assertCount(1, $executions);
        $this->assertStringNotContainsString('private', $executions->first()->toJson());
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

    private function billingPayload(): array
    {
        return [
            'id' => 'evt_release_1',
            'type' => 'customer.subscription.updated',
            'customer' => [
                'id' => 'cus_release_1',
                'email' => 'release@example.test',
                'metadata' => [
                    'safe' => 'yes',
                    'card_token' => 'hidden',
                ],
            ],
            'subscription' => [
                'id' => 'sub_release_1',
                'plan' => 'local_premium',
                'status' => 'active',
                'metadata' => [
                    'safe' => 'subscription',
                    'payment_method' => 'hidden',
                ],
            ],
            'invoice' => [
                'id' => 'inv_release_1',
                'status' => 'paid',
                'metadata' => [
                    'safe' => 'invoice',
                    'secret' => 'hidden',
                ],
            ],
        ];
    }

    private function billingSignature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), 'release-secret');
    }

    private function staffWithPermissions(array $permissions): StaffUser
    {
        $staff = StaffUser::query()->create([
            'name' => 'Release Staff',
            'email' => uniqid('staff-', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Release Role',
            'slug' => uniqid('release-role-', false),
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
