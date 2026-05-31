<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Models\DomainOnboardingAudit;
use App\Services\Domain\DomainDnsReadinessService;
use App\Services\Domain\DomainOnboardingService;
use App\Services\Domain\DomainPoolService;
use App\Services\Domain\DomainSafetyCheckService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainOnboardingReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        config([
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.onboarding.dns_readiness.mx' => true,
            'domains.onboarding.dns_readiness.spf' => true,
            'domains.onboarding.dns_readiness.dkim' => true,
            'domains.onboarding.dns_readiness.dmarc' => true,
            'domains.onboarding.dns_readiness.provider_mapping' => true,
            'mail-providers.providers.local.enabled' => true,
            'mail-providers.activation.states.local' => 'active',
        ]);
    }

    public function test_onboarding_migrations_and_audit_model_work(): void
    {
        $this->assertTrue(Schema::hasColumn('domains', 'onboarding_state'));
        $this->assertTrue(Schema::hasTable('domain_onboarding_audits'));
        $this->assertTrue(Schema::hasColumns('domain_onboarding_audits', [
            'domain_id',
            'domain_name',
            'previous_state',
            'new_state',
            'reason',
            'metadata',
        ]));

        $domain = $this->domain();
        $audit = DomainOnboardingAudit::query()->create([
            'domain_id' => $domain->id,
            'domain_name' => $domain->domain,
            'previous_state' => DomainOnboardingState::Draft,
            'new_state' => DomainOnboardingState::Validating,
            'metadata' => ['safe' => true],
        ]);

        $this->assertTrue($audit->domain->is($domain));
        $this->assertSame(DomainOnboardingState::Validating, $audit->fresh()->new_state);
        $this->assertSame(['safe' => true], $audit->fresh()->metadata);
    }

    public function test_dns_readiness_uses_configuration_and_metadata_only(): void
    {
        $domain = $this->domain(metadata: [
            'onboarding' => [
                'dns_readiness' => ['dkim' => false],
            ],
        ]);

        $report = app(DomainDnsReadinessService::class)->review($domain);

        $this->assertFalse($report['ready']);
        $this->assertContains('dkim', array_column($report['pending'], 'name'));
        $this->assertContains('mx', array_column($report['passed'], 'name'));
    }

    public function test_safety_checks_report_blockers_warnings_and_recommendations(): void
    {
        config(['domains.onboarding.dns_readiness.mx' => false]);
        $domain = $this->domain(healthScore: 20);

        $report = app(DomainSafetyCheckService::class)->report($domain);

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('dns_readiness', array_column($report['blockers'], 'name'));
        $this->assertContains('domain_pool_compatibility', array_column($report['blockers'], 'name'));
        $this->assertContains('Confirm MX readiness manually.', $report['recommendations']);
    }

    public function test_onboarding_lifecycle_creates_audits_and_observability_events(): void
    {
        $domain = $this->domain();
        $onboarding = app(DomainOnboardingService::class);

        $onboarding->start($domain, 'Manual onboarding started.');
        $review = $onboarding->validate($domain->refresh());
        $onboarding->activate($domain->refresh());
        $onboarding->suspend($domain->refresh(), 'Manual suspension.');

        $this->assertSame([], $review['blockers']);
        $this->assertSame(DomainOnboardingState::Suspended, $domain->refresh()->onboarding_state);
        $this->assertSame(DomainStatus::Suspended, $domain->status);
        $this->assertSame(4, $domain->onboardingAudits()->count());
        foreach ([
            'domain_onboarding_started',
            'domain_onboarding_validated',
            'domain_onboarding_ready',
            'domain_onboarding_activated',
            'domain_onboarding_suspended',
        ] as $eventType) {
            $this->assertDatabaseHas('operations_events', ['event_type' => $eventType]);
        }
    }

    public function test_blocked_validation_creates_audit_and_event(): void
    {
        config(['domains.onboarding.dns_readiness.mx' => false]);
        $domain = $this->domain();

        $review = app(DomainOnboardingService::class)->validate($domain);

        $this->assertNotEmpty($review['blockers']);
        $this->assertSame(DomainOnboardingState::Validating, $domain->refresh()->onboarding_state);
        $this->assertDatabaseHas('domain_onboarding_audits', [
            'domain_id' => $domain->id,
            'new_state' => DomainOnboardingState::Validating->value,
        ]);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'domain_onboarding_blocked']);
    }

    public function test_domain_pool_only_assigns_onboarding_active_domains(): void
    {
        $this->domain('draft-domain.test');
        $active = $this->domain('active-domain.test', onboardingState: DomainOnboardingState::Active);

        $selected = app(DomainPoolService::class)->selectDomain();

        $this->assertSame($active->domain, $selected);
    }

    public function test_activation_review_covers_required_compatibility_checks(): void
    {
        $report = app(DomainOnboardingService::class)->activationReview($this->domain());
        $checks = array_column($report['checks'], 'name');

        $this->assertContains('domain_pool_compatibility', $checks);
        $this->assertContains('provider_compatibility', $checks);
        $this->assertContains('feature_gate_compatibility', $checks);
        $this->assertContains('organization_compatibility', $checks);
    }

    public function test_onboarding_status_command_outputs_safe_summary(): void
    {
        $this->domain('ready-domain.test', onboardingState: DomainOnboardingState::Active);

        $this->artisan('domain:onboarding-status')
            ->expectsOutput('Domain onboarding status: READY')
            ->expectsOutput('Domains: 1')
            ->expectsOutput('Ready: 1')
            ->expectsOutput('Blockers: 0')
            ->expectsOutput('State active: 1')
            ->doesntExpectOutputToContain('ready-domain.test')
            ->assertSuccessful();
    }

    public function test_onboarding_status_command_fails_when_domain_is_blocked(): void
    {
        config(['domains.onboarding.dns_readiness.mx' => false]);
        $this->domain();

        $this->artisan('domain:onboarding-status')
            ->expectsOutput('Domain onboarding status: BLOCKED')
            ->expectsOutputToContain('Blockers:')
            ->assertFailed();
    }

    private function domain(
        string $name = 'onboarding-domain.test',
        DomainOnboardingState $onboardingState = DomainOnboardingState::Draft,
        int $healthScore = 100,
        array $metadata = [],
    ): Domain {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => $name,
            'status' => $onboardingState === DomainOnboardingState::Active
                ? DomainStatus::Active
                : DomainStatus::Inactive,
            'onboarding_state' => $onboardingState,
            'type' => DomainType::Public,
            'tier' => DomainTier::Free,
            'priority' => 100,
            'health_score' => $healthScore,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'metadata' => $metadata,
        ]);
    }
}
