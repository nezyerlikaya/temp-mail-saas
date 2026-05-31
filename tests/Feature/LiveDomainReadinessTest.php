<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Services\Domain\DomainActivationReviewService;
use App\Services\Domain\DomainPoolService;
use App\Services\Domain\DomainRollbackReadinessService;
use App\Services\Domain\LiveDomainReadinessService;
use App\Services\Mail\PublicMailboxService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveDomainReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        config([
            'domains.onboarding.safety.warn_on_test_domain' => false,
            'domains.public_mailbox.default_domain' => 'fallback-live.test',
            'domains.public_mailbox.allowed_domains' => ['primary-live.test', 'fallback-live.test'],
            'domains.live_activation.rollback.fallback_domain' => 'fallback-live.test',
            'features-gates.plans.free.allowed_domains' => ['primary-live.test', 'fallback-live.test'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.activation.states.mailgun' => 'active',
        ]);

        $this->domain('primary-live.test');
        $this->domain('fallback-live.test');
    }

    public function test_live_domain_readiness_service_returns_ready_state(): void
    {
        $report = app(LiveDomainReadinessService::class)->report('primary-live.test');

        $this->assertSame('ready', $report['status']);
        $this->assertSame(1, $report['domain_count']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame([], $report['rollback']['blockers']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'live_domain_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'live_domain_review_ready']);
    }

    public function test_activation_review_uses_dns_configuration_only(): void
    {
        $domain = Domain::query()->where('domain', 'primary-live.test')->firstOrFail();
        $report = app(DomainActivationReviewService::class)->review($domain);

        $this->assertSame([], $report['blockers']);
        $this->assertContains('mx_readiness', array_column($report['passed'], 'name'));

        $domain->forceFill(['metadata' => [
            'onboarding' => [
                'provider' => 'mailgun',
                'dns_readiness' => ['dkim' => false],
            ],
        ]])->save();

        $blocked = app(DomainActivationReviewService::class)->review($domain->refresh());
        $this->assertContains('dkim_readiness', array_column($blocked['blockers'], 'name'));
    }

    public function test_domain_rollback_readiness_reviews_fallback_and_mailbox_safety(): void
    {
        $domain = Domain::query()->where('domain', 'primary-live.test')->firstOrFail();
        $report = app(DomainRollbackReadinessService::class)->report($domain);

        $this->assertSame([], $report['blockers']);
        $this->assertContains('fallback_domain_ready', array_column($report['passed'], 'name'));
        $this->assertContains('mailbox_generation_safety', array_column($report['passed'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'live_domain_rollback_reviewed']);
    }

    public function test_domain_pool_and_mailbox_generation_exclude_suspended_domain(): void
    {
        $suspended = $this->domain('suspended-live.test', DomainStatus::Suspended, DomainOnboardingState::Suspended);
        config([
            'domains.public_mailbox.allowed_domains' => ['primary-live.test', 'fallback-live.test', 'suspended-live.test'],
            'features-gates.plans.free.allowed_domains' => ['primary-live.test', 'fallback-live.test', 'suspended-live.test'],
        ]);

        $pool = app(DomainPoolService::class);
        $mailboxes = app(PublicMailboxService::class);

        $this->assertNotContains($suspended->domain, $pool->eligibleDomains()->pluck('domain')->all());
        $this->assertNull($mailboxes->normalize('review@suspended-live.test'));
        $this->assertSame('review@primary-live.test', $mailboxes->normalize('REVIEW@PRIMARY-LIVE.TEST'));
    }

    public function test_live_domain_readiness_creates_safe_review_audits(): void
    {
        $domain = Domain::query()->where('domain', 'primary-live.test')->firstOrFail();
        $metadata = $domain->metadata;
        data_set($metadata, 'onboarding.dns_secret', 'dns-secret-value');
        $domain->forceFill(['metadata' => $metadata])->save();

        $report = app(LiveDomainReadinessService::class)->report('primary-live.test');

        foreach (['readiness_review', 'activation_recommendation', 'suspension_recommendation'] as $reviewType) {
            $this->assertDatabaseHas('domain_onboarding_audits', [
                'domain_name' => 'primary-live.test',
                'review_type' => $reviewType,
            ]);
        }

        $this->assertStringNotContainsString('dns-secret-value', json_encode($report));
    }

    public function test_live_domain_readiness_command_outputs_safe_summary(): void
    {
        $this->artisan('domain:live-readiness --domain=primary-live.test')
            ->expectsOutput('Live domain readiness: READY')
            ->expectsOutput('Domains reviewed: 1')
            ->expectsOutput('Blockers: 0')
            ->doesntExpectOutputToContain('primary-live.test')
            ->doesntExpectOutputToContain('dns-secret-value')
            ->assertSuccessful();
    }

    public function test_live_domain_readiness_command_fails_when_dns_readiness_is_missing(): void
    {
        $domain = Domain::query()->where('domain', 'primary-live.test')->firstOrFail();
        $domain->forceFill(['metadata' => [
            'onboarding' => [
                'provider' => 'mailgun',
                'dns_readiness' => ['mx' => false],
            ],
        ]])->save();

        $this->artisan('domain:live-readiness --domain=primary-live.test')
            ->expectsOutput('Live domain readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: domain.activation_readiness')
            ->assertFailed();
    }

    private function domain(
        string $name,
        DomainStatus $status = DomainStatus::Active,
        DomainOnboardingState $onboardingState = DomainOnboardingState::Active,
    ): Domain {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => $name,
            'status' => $status,
            'onboarding_state' => $onboardingState,
            'type' => DomainType::Public,
            'tier' => DomainTier::Free,
            'priority' => 10,
            'health_score' => 100,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'metadata' => [
                'onboarding' => [
                    'provider' => 'mailgun',
                    'dns_readiness' => [
                        'mx' => true,
                        'spf' => true,
                        'dkim' => true,
                        'dmarc' => true,
                        'provider_mapping' => true,
                    ],
                ],
            ],
        ]);
    }
}
