<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\RetentionTier;
use App\Models\Plan;
use App\Models\StaffUser;
use App\Models\User;
use App\Models\UserPlanAssignment;
use App\Services\Billing\FeatureGateService;
use App\Services\Mail\EmailRetentionService;
use App\Services\Mail\PublicMailboxService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use Tests\TestCase;

class PremiumPlanFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_plan_and_assignment_migrations_work(): void
    {
        $user = User::factory()->create();
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        $assignment = UserPlanAssignment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $premium->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'notes' => 'Manual test assignment.',
        ]);

        $this->assertDatabaseHas('plans', ['slug' => 'premium']);
        $this->assertDatabaseHas('user_plan_assignments', [
            'id' => $assignment->id,
            'user_id' => $user->id,
            'plan_id' => $premium->id,
        ]);
        $this->assertTrue($assignment->isActive());
    }

    public function test_plan_seeder_is_idempotent_and_plan_helpers_work(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseCount('plans', 3);
        $this->assertTrue(Plan::query()->where('slug', 'free')->firstOrFail()->isFree());
        $this->assertTrue(Plan::query()->where('slug', 'member')->firstOrFail()->isMember());
        $this->assertTrue(Plan::query()->where('slug', 'premium')->firstOrFail()->isPremium());
    }

    public function test_plan_relationships_and_staff_manual_assignment_work(): void
    {
        $user = User::factory()->create();
        $staff = StaffUser::query()->create([
            'name' => 'Plan Manager',
            'email' => 'plans@example.test',
            'password' => 'password',
        ]);
        $member = Plan::query()->where('slug', 'member')->firstOrFail();

        $assignment = UserPlanAssignment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $member->id,
            'assigned_by_staff_id' => $staff->id,
        ]);

        $this->assertTrue($user->planAssignments->contains($assignment));
        $this->assertTrue($member->assignments->contains($assignment));
        $this->assertTrue($member->users->contains($user));
        $this->assertTrue($staff->assignedPlanAssignments->contains($assignment));
        $this->assertTrue($user->activePlan->isMember());
        $this->assertTrue($user->isMember());
    }

    public function test_default_free_plan_and_feature_fallback_work(): void
    {
        $features = app(FeatureGateService::class);

        $this->assertSame('free', $features->currentPlan());
        $this->assertSame('short', $features->featureValue('retention_tier'));
        $this->assertSame('fallback', $features->featureValue('missing_feature', null, 'fallback'));
        $this->assertFalse($features->hasFeature('priority_processing_placeholder'));
    }

    public function test_active_premium_assignment_resolves_premium_features(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Free]);
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();
        UserPlanAssignment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $premium->id,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addMonth(),
        ]);

        $features = app(FeatureGateService::class);

        $this->assertSame('premium', $features->currentPlan($user));
        $this->assertTrue($features->hasFeature('priority_processing_placeholder', $user));
        $this->assertSame(5000, $features->featureValue('polling_interval', $user));
        $this->assertTrue($user->isPremium());
    }

    public function test_expired_assignment_falls_back_to_account_tier(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Member]);
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();
        UserPlanAssignment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $premium->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertSame('member', app(FeatureGateService::class)->currentPlan($user));
        $this->assertTrue($user->isMember());
    }

    public function test_retention_mapping_is_plan_aware(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $retention = app(EmailRetentionService::class);

        $this->assertSame(RetentionTier::Short, $retention->tierForUser());
        $this->assertSame(RetentionTier::Premium, $retention->tierForUser($user));
    }

    public function test_public_inbox_generation_remains_functional(): void
    {
        $request = Request::create('/inbox/generate', 'POST');
        $request->setLaravelSession(new Store('test', new ArraySessionHandler(120), (string) Str::uuid()));

        $mailbox = app(PublicMailboxService::class)->generate($request);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}@example\.test$/', $mailbox);
        $this->get('/inbox')->assertOk()->assertSee('Public Inbox');
    }

    public function test_existing_auth_abuse_installer_and_admin_routes_still_behave(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->getJson('/inbox/messages')->assertOk();
        $this->get('/install')->assertOk();
        $this->get('/admin')->assertForbidden();
    }
}
