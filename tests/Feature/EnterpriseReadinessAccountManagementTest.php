<?php

namespace Tests\Feature;

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Enums\OrganizationStatus;
use App\Models\OperationsEvent;
use App\Models\Organization;
use App\Models\User;
use App\Services\Enterprise\AccountGovernanceService;
use App\Services\Enterprise\EnterpriseAccountHealthService;
use App\Services\Enterprise\EnterpriseCertificationService;
use App\Services\Enterprise\MembershipIntelligenceService;
use App\Services\Enterprise\OrganizationLifecycleService;
use App\Services\Enterprise\OrganizationService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnterpriseReadinessAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_enterprise_account_health_reports_healthy_aggregate_state(): void
    {
        $this->organization();

        $report = app(EnterpriseAccountHealthService::class)->review();

        $this->assertSame('healthy', $report['state']);
        $this->assertSame(1, $report['organizations']['total']);
        $this->assertSame(1, $report['memberships']['total']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'enterprise_review_completed']);
    }

    public function test_enterprise_account_health_reports_risk_for_suspended_organizations(): void
    {
        $this->organization(OrganizationStatus::Suspended);
        $this->organization(OrganizationStatus::Suspended);

        $report = app(EnterpriseAccountHealthService::class)->review();

        $this->assertSame('risk', $report['state']);
        $this->assertGreaterThanOrEqual(5, $report['score']);
    }

    public function test_organization_lifecycle_review_supports_warning_and_blocked_states(): void
    {
        config(['enterprise.readiness.lifecycle.growth_ready' => false]);

        $this->assertSame('warning', app(OrganizationLifecycleService::class)->review()['status']);

        config(['enterprise.readiness.lifecycle.suspension_ready' => false]);

        $this->assertSame('blocked', app(OrganizationLifecycleService::class)->review()['status']);
    }

    public function test_account_governance_reviews_roles_permissions_and_ownership(): void
    {
        $this->organization();

        $report = app(AccountGovernanceService::class)->review();

        $this->assertSame('ready', $report['status']);
        $this->assertSame(1, $report['role_distribution']['owner']);
        $this->assertSame(0, $report['organizations_without_owners']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'governance_review_completed']);
    }

    public function test_account_governance_warns_for_ownerless_organization_without_exposing_details(): void
    {
        Organization::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ownerless Org',
            'slug' => 'ownerless-org',
            'status' => OrganizationStatus::Active,
        ]);

        $report = app(AccountGovernanceService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertSame(1, $report['organizations_without_owners']);
        $this->assertStringNotContainsString('Ownerless Org', json_encode($report, JSON_THROW_ON_ERROR));
    }

    public function test_membership_intelligence_reports_growth_and_inactive_trends(): void
    {
        $organization = $this->organization();
        $member = User::factory()->create();
        $membership = app(OrganizationService::class)->addMember($organization, $member, OrganizationMemberRole::Member);
        $membership->forceFill(['status' => OrganizationMemberStatus::Removed])->save();

        $report = app(MembershipIntelligenceService::class)->report();

        $this->assertSame('warning', $report['status']);
        $this->assertSame(1, $report['inactive_memberships']);
        $this->assertSame(2, $report['membership_growth']['joined_count']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'membership_review_completed']);
    }

    public function test_enterprise_certification_aggregates_reviews_and_records_certification(): void
    {
        $this->organization();

        $report = app(EnterpriseCertificationService::class)->report();

        $this->assertSame('certified', $report['status']);
        $this->assertSame('healthy', $report['account_health']['state']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'enterprise_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'enterprise_certified']);
    }

    public function test_enterprise_command_outputs_safe_aggregate_summary(): void
    {
        $this->organization();

        $this->artisan('system:enterprise-status')
            ->expectsOutput('Enterprise readiness: CERTIFIED')
            ->expectsOutput('Account health: HEALTHY')
            ->expectsOutput('Governance: READY')
            ->doesntExpectOutputToContain('private@example.com')
            ->doesntExpectOutputToContain('member@example.test')
            ->assertSuccessful();
    }

    public function test_enterprise_reports_and_events_do_not_expose_member_details(): void
    {
        $organization = $this->organization();
        $member = User::factory()->create(['email' => 'private@example.com', 'name' => 'Private Member']);
        app(OrganizationService::class)->addMember($organization, $member);

        $encoded = json_encode(app(EnterpriseCertificationService::class)->report(), JSON_THROW_ON_ERROR);
        $events = OperationsEvent::query()->where('source', 'enterprise-readiness')->get()->toJson();

        $this->assertStringNotContainsString('private@example.com', $encoded);
        $this->assertStringNotContainsString('Private Member', $encoded);
        $this->assertStringNotContainsString('private@example.com', $events);
        $this->assertStringNotContainsString('Private Member', $events);
    }

    private function organization(OrganizationStatus $status = OrganizationStatus::Active): Organization
    {
        return app(OrganizationService::class)->create([
            'name' => 'Enterprise '.Str::random(8),
            'status' => $status,
        ], User::factory()->create());
    }
}
