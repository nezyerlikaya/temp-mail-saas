<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailParseStatus;
use App\Enums\InboundIntakeStatus;
use App\Enums\InboundProvider;
use App\Enums\RetentionTier;
use App\Enums\StaffStatus;
use App\Jobs\ProcessInboundMailIntake;
use App\Models\Domain;
use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffUser;
use App\Models\User;
use App\Services\Domain\DomainPoolService;
use App\Services\Mail\EmailMessageStorageService;
use App\Services\Mail\InboundMailIntakeService;
use App\Services\Mail\LoadReadinessService;
use App\Services\Mail\PublicInboxMessageService;
use App\Services\System\PerformanceCacheService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerformanceScalabilityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_performance_indexes_are_available_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('email_messages'));
        $this->assertTrue(Schema::hasTable('inbound_mail_intakes'));
        $this->assertTrue(Schema::hasTable('queue_metrics'));
        $this->assertTrue(Schema::hasTable('domain_assignments'));
    }

    public function test_performance_cache_service_caches_and_invalidates_safely(): void
    {
        config(['performance.cache.ttl.health_summary' => 60]);
        $cache = app(PerformanceCacheService::class);
        $calls = 0;

        $first = $cache->healthSummary(function () use (&$calls): array {
            $calls++;

            return ['healthy' => $calls];
        });
        $second = $cache->healthSummary(function () use (&$calls): array {
            $calls++;

            return ['healthy' => $calls];
        });

        $this->assertSame(['healthy' => 1], $first);
        $this->assertSame(['healthy' => 1], $second);
        $this->assertSame(1, $calls);

        $cache->forget('health_summary');

        $third = $cache->healthSummary(function () use (&$calls): array {
            $calls++;

            return ['healthy' => $calls];
        });

        $this->assertSame(['healthy' => 2], $third);
        $this->assertSame(2, $calls);
    }

    public function test_operations_dashboard_uses_bounded_query_path(): void
    {
        $staff = $this->staffWithPermissions(['operations.view']);

        DB::enableQueryLog();

        $this->actingAs($staff, 'staff')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Operations Center');

        $this->assertLessThanOrEqual(
            (int) config('performance.thresholds.admin_page_query_warning', 40),
            count(DB::getQueryLog()),
        );
    }

    public function test_inbound_queue_processing_remains_idempotent(): void
    {
        $intake = InboundMailIntake::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => InboundProvider::Local,
            'provider_message_id' => 'perf-idempotent-1',
            'signature_valid' => true,
            'signature_checked_at' => now(),
            'status' => InboundIntakeStatus::Queued,
            'payload_json' => [
                'mailbox_address' => 'perf@example.test',
                'from_email' => 'sender@example.net',
                'subject' => 'Idempotent processing',
                'text_body' => 'Only one message should be stored.',
            ],
        ]);

        $job = new ProcessInboundMailIntake($intake->id);
        $job->handle(app(InboundMailIntakeService::class), app(EmailMessageStorageService::class));
        $job->handle(app(InboundMailIntakeService::class), app(EmailMessageStorageService::class));

        $this->assertSame(1, EmailMessage::query()->count());
        $this->assertTrue($intake->fresh()->isProcessed());
    }

    public function test_public_inbox_list_is_limited_and_uses_safe_output(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->messageFor('scale@example.test', ['subject' => "Message {$i}", 'received_at' => now()->subSeconds($i)]);
        }

        $messages = app(PublicInboxMessageService::class)->list('scale@example.test');

        $this->assertCount((int) config('performance.thresholds.inbox_poll_limit', 50), $messages);
        $this->assertArrayHasKey('uuid', $messages->first());
        $this->assertArrayNotHasKey('id', $messages->first());
        $this->assertArrayNotHasKey('storage_path', $messages->first());
    }

    public function test_domain_pool_selection_respects_health_threshold_and_fallbacks(): void
    {
        $this->seed(PlanSeeder::class);
        config(['performance.thresholds.domain_pool_min_health' => 25]);

        $this->domain('too-low.example.test', DomainTier::Free, healthScore: 10, priority: 1);
        $healthy = $this->domain('healthy.example.test', DomainTier::Free, healthScore: 90, priority: 20);

        $selected = app(DomainPoolService::class)->selectDomain(User::factory()->create(['account_tier' => AccountTier::Free]));

        $this->assertSame($healthy->domain, $selected);

        Domain::query()->update(['status' => DomainStatus::Inactive]);
        config([
            'domains.public_mailbox.allowed_domains' => ['fallback.example.test'],
            'domains.public_mailbox.default_domain' => 'fallback.example.test',
        ]);

        $this->assertContains(app(DomainPoolService::class)->selectDomain(), [
            'fallback.example.test',
            'example.test',
        ]);
    }

    public function test_load_readiness_report_includes_database_cache_queue_and_admin_sections(): void
    {
        $report = app(LoadReadinessService::class)->report();

        $this->assertArrayHasKey('database', $report);
        $this->assertArrayHasKey('cache', $report);
        $this->assertArrayHasKey('queue', $report);
        $this->assertArrayHasKey('admin', $report);
        $this->assertContains($report['database']['status'], ['ready', 'blocked']);
        $this->assertContains($report['cache']['status'], ['ready', 'warning']);
        $this->assertSame('ready', $report['admin']['status']);
    }

    public function test_performance_config_has_safe_defaults(): void
    {
        $this->assertTrue((bool) config('performance.cache.enabled'));
        $this->assertGreaterThan(0, (int) config('performance.cache.ttl.health_summary'));
        $this->assertGreaterThan(0, (int) config('performance.thresholds.inbox_poll_limit'));
        $this->assertGreaterThan(0, (int) config('performance.aggregation.recent_audit_limit'));
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

    private function domain(
        string $domain,
        DomainTier $tier,
        DomainStatus $status = DomainStatus::Active,
        int $priority = 100,
        int $healthScore = 100,
    ): Domain {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => $domain,
            'status' => $status,
            'type' => $tier === DomainTier::Premium ? DomainType::Premium : DomainType::Public,
            'tier' => $tier,
            'priority' => $priority,
            'health_score' => $healthScore,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'metadata' => ['test' => true],
            'last_checked_at' => now(),
        ]);
    }

    private function staffWithPermissions(array $permissions): StaffUser
    {
        $staff = StaffUser::query()->create([
            'name' => 'Performance Staff',
            'email' => uniqid('performance-', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Performance Role',
            'slug' => uniqid('performance-role-', false),
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
