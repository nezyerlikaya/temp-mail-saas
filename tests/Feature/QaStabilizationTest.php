<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\AutomationActionType;
use App\Enums\AutomationRuleStatus;
use App\Enums\AutomationTriggerType;
use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\LanguageDirection;
use App\Enums\RetentionTier;
use App\Enums\StaffStatus;
use App\Models\AutomationRule;
use App\Models\BillingCustomer;
use App\Models\BillingWebhookEvent;
use App\Models\Domain;
use App\Models\EmailMessage;
use App\Models\Language;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffUser;
use App\Models\Translation;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Automation\AutomationEngine;
use App\Services\Billing\BillingService;
use App\Services\Domain\DomainPoolService;
use App\Services\System\EnvironmentWriterService;
use App\Services\System\InstallerDatabaseService;
use App\Services\System\LocaleService;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaStabilizationTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerPath = storage_path('framework/testing/qa-stabilization');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('q', 32)),
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'domains.public_mailbox.default_domain' => 'example.test',
            'domains.public_mailbox.allowed_domains' => ['example.test'],
            'billing.providers.local.webhook_secret' => 'qa-secret',
        ]);

        File::put($this->installerPath.'/.env', "APP_KEY=configured\n");
        File::put($this->installerPath.'/install.lock', '{}');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_installer_handles_empty_and_corrupted_environment_files(): void
    {
        File::put($this->installerPath.'/.env', '');

        $this->assertTrue(app(LocaleService::class)->isValidLocale('en'));
        $this->get('/install')->assertOk();

        $result = (new EnvironmentWriterService($this->installerPath.'/.env'))->write([
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'APP_NAME' => 'Temp Mail SaaS',
            'BAD KEY' => 'ignored',
        ]);

        $contents = File::get($this->installerPath.'/.env');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('APP_KEY=', $contents);
        $this->assertStringContainsString('APP_NAME="Temp Mail SaaS"', $contents);
        $this->assertStringNotContainsString('BAD KEY', $contents);
    }

    public function test_installer_database_failure_returns_user_safe_status(): void
    {
        config([
            'database.connections.missing_connection' => [
                'driver' => 'not-real',
            ],
        ]);

        $status = app(InstallerDatabaseService::class)->status('missing_connection');

        $this->assertFalse($status['ok']);
        $this->assertSame('Database driver is not available.', $status['message']);
        $this->assertStringNotContainsString('password', json_encode($status));
    }

    public function test_malformed_locale_config_falls_back_without_exception(): void
    {
        config([
            'tempmail.localization.supported_locales' => ['en', 123, '', '../tr'],
            'tempmail.localization.default_locale' => null,
            'tempmail.localization.fallback_locale' => 'en',
        ]);

        $locales = app(LocaleService::class);

        $this->assertSame(['en'], $locales->activeLocaleCodes());
        $this->assertSame('en', $locales->setApplicationLocale('not-valid'));
    }

    public function test_localization_empty_translation_falls_back_to_default_text(): void
    {
        $this->seed(LanguageSeeder::class);
        $english = Language::query()->where('code', 'en')->firstOrFail();

        Translation::query()->create([
            'language_id' => $english->id,
            'group' => 'app',
            'key' => 'empty',
            'value' => '',
            'is_custom' => true,
        ]);

        $this->assertSame('Fallback text', app(\App\Services\System\TranslationService::class)->get('app', 'empty', 'en', 'Fallback text'));
    }

    public function test_domain_pool_falls_back_when_inventory_is_empty_or_strategy_invalid(): void
    {
        $this->seed(PlanSeeder::class);
        config([
            'domains-pool.default_strategy' => 'not-a-strategy',
            'domains.public_mailbox.allowed_domains' => ['fallback.test'],
            'domains.public_mailbox.default_domain' => 'fallback.test',
            'features-gates.plans.free.allowed_domains' => ['fallback.test'],
        ]);

        $this->assertSame('fallback.test', app(DomainPoolService::class)->selectDomain());

        Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => 'inactive.test',
            'status' => DomainStatus::Inactive,
            'type' => DomainType::Public,
            'tier' => DomainTier::Free,
            'priority' => 1,
            'health_score' => 100,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'last_checked_at' => now(),
        ]);

        $this->assertSame('fallback.test', app(DomainPoolService::class)->selectDomain());
    }

    public function test_public_inbox_missing_session_and_invalid_uuid_are_safe(): void
    {
        $message = $this->messageFor('current@example.test');

        $this->getJson('/inbox/messages')->assertOk()->assertJsonCount(0, 'messages');
        $this->getJson('/inbox/messages/not-a-uuid')->assertNotFound();

        $this->withSession(['public_inbox.mailbox' => 'current@example.test'])
            ->getJson('/inbox/messages/'.Str::uuid())
            ->assertNotFound();

        $this->withSession(['public_inbox.mailbox' => 'current@example.test'])
            ->getJson('/inbox/messages/'.$message->uuid)
            ->assertOk()
            ->assertJsonMissingPath('message.html_body');
    }

    public function test_billing_malformed_signed_payload_fails_without_persisting_customer(): void
    {
        $payload = [
            'id' => 'evt_bad_payload',
            'type' => 'customer.subscription.updated',
            'customer' => ['email' => 'missing-id@example.test'],
        ];

        $this->withHeader('X-Billing-Signature', hash_hmac('sha256', json_encode($payload), 'qa-secret'))
            ->postJson('/billing/webhooks/local', $payload)
            ->assertOk()
            ->assertJson(['ok' => false, 'status' => 'failed']);

        $this->assertSame(1, BillingWebhookEvent::query()->count());
        $this->assertSame(0, BillingCustomer::query()->count());
    }

    public function test_billing_metadata_sanitizer_handles_objects_and_nested_values(): void
    {
        $metadata = app(BillingService::class)->sanitizeMetadata([
            'safe' => 'value',
            'nested' => [
                'count' => 1,
                'token' => 'hidden',
            ],
            'object' => new \stdClass(),
        ]);

        $this->assertSame(['safe' => 'value', 'nested' => ['count' => 1], 'object' => null], $metadata);
    }

    public function test_api_malformed_and_disabled_tokens_have_consistent_responses(): void
    {
        $free = User::factory()->create(['account_tier' => AccountTier::Free]);
        $created = app(ApiKeyService::class)->create($free, 'Free key');

        $this->withHeader('Authorization', 'Bearer malformed')
            ->getJson('/api/v1/ping')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid API key.']);

        $this->withHeader('Authorization', 'Bearer '.$created['plain_text_key'])
            ->getJson('/api/v1/ping')
            ->assertForbidden()
            ->assertJson(['message' => 'API access is not enabled for this account.']);
    }

    public function test_automation_invalid_rule_action_is_contained_as_failed_execution(): void
    {
        $rule = AutomationRule::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Invalid action rule',
            'trigger_type' => AutomationTriggerType::ScheduledEvent,
            'condition_group' => null,
            'action_type' => AutomationActionType::Log,
            'priority' => 1,
            'status' => AutomationRuleStatus::Active,
            'metadata' => [],
        ]);
        DB::table('automation_rules')
            ->where('id', $rule->id)
            ->update(['action_type' => 'missing_action']);

        $executions = app(AutomationEngine::class)->evaluate(AutomationTriggerType::ScheduledEvent, ['scheduled' => true]);

        $this->assertCount(1, $executions);
        $this->assertSame('failed', $executions->first()->status->value);
    }

    public function test_admin_empty_state_pages_load_for_authorized_staff(): void
    {
        $staff = $this->staffWithPermissions([
            'operations.view',
            'health.view',
            'queue.view',
            'domains.view',
            'abuse.view',
            'billing.view',
            'audit.view',
            'localization.view',
        ]);

        foreach ([
            '/admin',
            '/admin/health',
            '/admin/queue',
            '/admin/domains?search=no-results',
            '/admin/abuse?search=no-results',
            '/admin/billing',
            '/admin/audit',
            '/admin/localization',
            '/admin/localization/translations?search=no-results',
        ] as $uri) {
            $this->actingAs($staff, 'staff')->get($uri)->assertOk();
        }
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
            'name' => 'QA Staff',
            'email' => uniqid('qa-staff-', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'QA Role',
            'slug' => uniqid('qa-role-', false),
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
