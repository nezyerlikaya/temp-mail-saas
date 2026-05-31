<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Enums\OrganizationStatus;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\FeatureGateService;
use App\Services\Enterprise\OrganizationService;
use App\Services\Enterprise\TenantContextService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnterpriseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_organizations_and_members_migrations_work(): void
    {
        $this->assertTrue(Schema::hasTable('organizations'));
        $this->assertTrue(Schema::hasColumns('organizations', [
            'uuid',
            'name',
            'slug',
            'status',
            'owner_user_id',
            'plan_id',
            'metadata',
        ]));
        $this->assertTrue(Schema::hasTable('organization_members'));
        $this->assertTrue(Schema::hasColumns('organization_members', [
            'organization_id',
            'user_id',
            'role',
            'status',
            'invited_by_user_id',
            'joined_at',
        ]));
    }

    public function test_organization_creation_assigns_owner_and_sanitizes_metadata(): void
    {
        $owner = User::factory()->create();
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        $organization = app(OrganizationService::class)->create([
            'name' => 'Acme Team',
            'plan_id' => $premium->id,
            'metadata' => [
                'industry' => 'testing',
                'secret_token' => 'hidden',
            ],
        ], $owner);

        $this->assertSame('acme-team', $organization->slug);
        $this->assertTrue($organization->isActive());
        $this->assertTrue($organization->owner->is($owner));
        $this->assertTrue($organization->plan->isPremium());
        $this->assertSame(['industry' => 'testing'], $organization->metadata);
        $this->assertTrue($organization->members()->firstOrFail()->isOwner());
        $this->assertTrue($owner->ownedOrganizations->contains($organization));
        $this->assertTrue($owner->organizations->contains($organization));
    }

    public function test_member_add_remove_and_membership_checks_work(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = app(OrganizationService::class)->create(['name' => 'Example Org'], $owner);

        $membership = app(OrganizationService::class)->addMember(
            $organization,
            $member,
            OrganizationMemberRole::Admin,
            $owner,
        );

        $this->assertSame(OrganizationMemberRole::Admin, $membership->role);
        $this->assertSame(OrganizationMemberStatus::Active, $membership->status);
        $this->assertTrue(app(OrganizationService::class)->isMember($organization, $member));
        $this->assertTrue($membership->invitedBy->is($owner));

        $this->assertTrue(app(OrganizationService::class)->removeMember($organization, $member));
        $this->assertFalse(app(OrganizationService::class)->isMember($organization, $member));
        $this->assertSame(OrganizationMemberStatus::Removed, $membership->fresh()->status);
    }

    public function test_tenant_context_can_be_set_for_valid_member(): void
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->create(['name' => 'Tenant Org'], $owner);
        $request = $this->requestWithSession();

        $this->assertTrue(app(TenantContextService::class)->set($organization, $owner, $request));
        $this->assertTrue(app(TenantContextService::class)->current($request, $owner)->is($organization));
    }

    public function test_tenant_context_rejects_invalid_member(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $organization = app(OrganizationService::class)->create(['name' => 'Private Org'], $owner);
        $request = $this->requestWithSession();

        $this->assertFalse(app(TenantContextService::class)->set($organization, $outsider, $request));
        $request->session()->put(config('enterprise.organizations.tenant_context_session_key'), $organization->id);
        $this->assertNull(app(TenantContextService::class)->current($request, $outsider));
        $this->assertFalse($request->session()->has(config('enterprise.organizations.tenant_context_session_key')));
    }

    public function test_feature_gate_resolves_organization_plan_before_user_plan(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Free]);
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();
        $organization = app(OrganizationService::class)->create([
            'name' => 'Premium Tenant',
            'plan_id' => $premium->id,
        ], $user);

        $features = app(FeatureGateService::class);

        $this->assertSame('premium', $features->currentPlan($user, $organization));
        $this->assertSame(5000, $features->featureValue('polling_interval', $user, organization: $organization));
        $this->assertTrue($features->hasFeature('api_enabled', $user, $organization));
    }

    public function test_inactive_organization_blocks_tenant_context(): void
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->create([
            'name' => 'Inactive Org',
            'status' => OrganizationStatus::Inactive,
        ], $owner);

        $this->assertFalse(app(TenantContextService::class)->set($organization, $owner, $this->requestWithSession()));
    }

    public function test_relationships_work_from_models(): void
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->create(['name' => 'Relations Org'], $owner);
        $membership = OrganizationMember::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->assertTrue($membership->organization->is($organization));
        $this->assertTrue($membership->user->is($owner));
        $this->assertTrue($organization->users->contains($owner));
        $this->assertTrue($owner->organizationMemberships->contains($membership));
    }

    public function test_existing_routes_still_work(): void
    {
        $this->getJson('/api/v1/ping')->assertUnauthorized();
        $this->get('/login')->assertOk();
        $this->get('/inbox')->assertOk();
        $this->assertContains($this->get('/install')->getStatusCode(), [200, 302]);
        $this->get('/admin')->assertForbidden();
    }

    private function requestWithSession(): Request
    {
        $request = Request::create('/tenant-context', 'POST');
        $request->setLaravelSession(new Store('test', new ArraySessionHandler(120), 'tenant-session'));

        return $request;
    }
}
