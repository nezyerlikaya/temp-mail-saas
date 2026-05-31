<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\User;
use App\Services\Domain\DomainPoolService;
use App\Services\Enterprise\OrganizationService;
use App\Services\Mail\PublicMailboxService;
use App\Services\Operations\DomainHealthService;
use Database\Seeders\DomainSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainPoolFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_domains_and_assignments_migrations_work(): void
    {
        $this->assertTrue(Schema::hasTable('domains'));
        $this->assertTrue(Schema::hasColumns('domains', [
            'uuid',
            'domain',
            'status',
            'type',
            'tier',
            'priority',
            'health_score',
            'assignment_strategy',
            'metadata',
            'last_checked_at',
        ]));
        $this->assertTrue(Schema::hasTable('domain_assignments'));
        $this->assertTrue(Schema::hasColumns('domain_assignments', [
            'domain_id',
            'mailbox_address',
            'user_id',
            'organization_id',
            'assigned_at',
            'metadata',
        ]));
    }

    public function test_domain_selection_uses_active_free_domain(): void
    {
        $free = $this->domain('free-domain.test', DomainTier::Free, priority: 10);
        $this->domain('member-domain.test', DomainTier::Member, priority: 1);

        $selected = app(DomainPoolService::class)->selectDomain(User::factory()->create(['account_tier' => AccountTier::Free]));

        $this->assertSame($free->domain, $selected);
    }

    public function test_inactive_domains_are_excluded(): void
    {
        $this->domain('inactive-domain.test', DomainTier::Free, status: DomainStatus::Inactive, priority: 1);
        $active = $this->domain('active-domain.test', DomainTier::Free, priority: 50);

        $selected = app(DomainPoolService::class)->selectDomain(User::factory()->create(['account_tier' => AccountTier::Free]));

        $this->assertSame($active->domain, $selected);
    }

    public function test_plan_filtering_allows_premium_domains_for_premium_users(): void
    {
        $this->domain('free-domain.test', DomainTier::Free, priority: 100);
        $premium = $this->domain('premium-domain.test', DomainTier::Premium, priority: 1);

        $selected = app(DomainPoolService::class)->selectDomain(User::factory()->create(['account_tier' => AccountTier::Premium]));

        $this->assertSame($premium->domain, $selected);
    }

    public function test_organization_plan_filtering_overrides_user_plan(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Free]);
        $premiumPlan = Plan::query()->where('slug', 'premium')->firstOrFail();
        $organization = app(OrganizationService::class)->create([
            'name' => 'Premium Org',
            'plan_id' => $premiumPlan->id,
        ], $user);
        $this->domain('free-domain.test', DomainTier::Free, priority: 100);
        $premium = $this->domain('premium-org-domain.test', DomainTier::Premium, priority: 1);

        $selected = app(DomainPoolService::class)->selectDomain($user, $organization);

        $this->assertSame($premium->domain, $selected);
    }

    public function test_assignment_history_is_created_without_secrets(): void
    {
        $domain = $this->domain('history-domain.test', DomainTier::Free);
        $user = User::factory()->create();

        $assignment = app(DomainPoolService::class)->recordAssignment($domain, 'abc@history-domain.test', $user, metadata: [
            'source' => 'test',
            'secret' => 'hidden',
        ]);

        $this->assertDatabaseHas('domain_assignments', [
            'id' => $assignment->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame(['source' => 'test'], $assignment->metadata);
        $this->assertTrue($assignment->domain->is($domain));
        $this->assertTrue($assignment->user->is($user));
    }

    public function test_health_score_helpers_work(): void
    {
        $domain = $this->domain('health-domain.test', DomainTier::Free);
        $health = app(DomainHealthService::class);

        $this->assertSame(100, $health->calculateHealthScore('health-domain.test'));
        $this->assertSame(25, $health->calculateHealthScore('not valid domain'));
        $this->assertSame(100, $health->markHealthy($domain)->health_score);
        $this->assertLessThan(80, $health->markWarning($domain)->health_score);
        $this->assertLessThan(50, $health->markCritical($domain)->health_score);
    }

    public function test_mailbox_generation_uses_domain_pool_and_records_assignment(): void
    {
        $this->domain('mailbox-domain.test', DomainTier::Free);
        $request = Request::create('/inbox/generate', 'POST');
        $request->setLaravelSession(new Store('test', new ArraySessionHandler(120), (string) Str::uuid()));

        $mailbox = app(PublicMailboxService::class)->generate($request);

        $this->assertStringEndsWith('@mailbox-domain.test', $mailbox);
        $this->assertDatabaseHas('domain_assignments', [
            'mailbox_address' => $mailbox,
        ]);
    }

    public function test_domain_seeder_is_idempotent(): void
    {
        $this->seed(DomainSeeder::class);
        $this->seed(DomainSeeder::class);

        $this->assertSame(1, Domain::query()->where('domain', 'example-temp.test')->count());
        $this->assertSame(3, Domain::query()->count());
    }

    public function test_existing_routes_still_work(): void
    {
        $this->getJson('/api/v1/ping')->assertUnauthorized();
        $this->get('/login')->assertOk();
        $this->get('/inbox')->assertOk();
        $this->assertContains($this->get('/install')->getStatusCode(), [200, 302]);
        $this->get('/admin')->assertForbidden();
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
            'onboarding_state' => DomainOnboardingState::Active,
            'type' => $tier === DomainTier::Premium ? DomainType::Premium : DomainType::Public,
            'tier' => $tier,
            'priority' => $priority,
            'health_score' => $healthScore,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'metadata' => ['test' => true],
            'last_checked_at' => now(),
        ]);
    }
}
