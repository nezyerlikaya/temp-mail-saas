<?php

namespace Tests\Feature;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Models\Incident;
use App\Services\System\LoadScenarioService;
use App\Services\System\ProductionLoadValidationService;
use App\Services\System\StressReadinessService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionLoadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        config([
            'domains.public_mailbox.default_domain' => 'load.test',
            'domains.public_mailbox.allowed_domains' => ['load.test'],
            'features-gates.plans.free.allowed_domains' => ['load.test'],
            'load-testing.thresholds.queue_pending_warning' => 10,
            'load-testing.thresholds.queue_pending_blocker' => 100,
            'load-testing.thresholds.failed_jobs_warning' => 1,
            'load-testing.thresholds.active_domain_minimum' => 1,
            'load-testing.thresholds.operations_recent_metric_minimum' => 0,
            'mail-providers.activation.readiness.providers' => ['mailgun'],
            'mail-providers.activation.states.mailgun' => 'active',
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'load-secret',
            'inbound.queue.name' => 'inbound-mail',
            'operations.queue_names' => ['inbound-mail'],
            'performance.thresholds.inbox_poll_limit' => 50,
            'retention.cleanup_chunk_size' => 100,
        ]);
    }

    public function test_load_validation_service_reports_ready(): void
    {
        $this->domain();

        $report = app(ProductionLoadValidationService::class)->report();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertContains('queue_first_handoff', array_column($report['checks'], 'name'));
        $this->assertContains('domain_pool_suspended_exclusion', array_column($report['checks'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'load_validation_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'load_validation_ready']);
    }

    public function test_queue_capacity_review_blocks_large_backlog(): void
    {
        config([
            'load-testing.thresholds.queue_pending_warning' => 1,
            'load-testing.thresholds.queue_pending_blocker' => 1,
        ]);
        $this->domain();
        DB::table('jobs')->insert([
            'queue' => 'inbound-mail',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $report = app(ProductionLoadValidationService::class)->report();

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('queue_backlog', array_column($report['blockers'], 'name'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'load_validation_blocked']);
    }

    public function test_inbox_provider_and_domain_pool_scalability_reviews_are_present(): void
    {
        $this->domain();

        $checks = array_column(app(ProductionLoadValidationService::class)->report()['checks'], 'name');

        $this->assertContains('polling_rate_limits', $checks);
        $this->assertContains('mailbox_generation_limits', $checks);
        $this->assertContains('provider_registry_ready', $checks);
        $this->assertContains('duplicate_protection', $checks);
        $this->assertContains('domain_pool_active_filtering', $checks);
        $this->assertContains('assignment_history_efficiency', $checks);
        $this->assertContains('monitoring_enabled', $checks);
    }

    public function test_stress_readiness_service_reports_safe_recommendations(): void
    {
        config(['retention.cleanup_chunk_size' => 10]);

        $report = app(StressReadinessService::class)->report();

        $this->assertSame('warning', $report['status']);
        $this->assertContains('cleanup_throughput_assumption', array_column($report['warnings'], 'name'));
        $this->assertNotEmpty($report['recommendations']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'stress_review_warning']);
    }

    public function test_load_scenario_service_documents_without_generating_traffic(): void
    {
        $summary = app(LoadScenarioService::class)->summary();

        $this->assertSame('ready', $summary['status']);
        $this->assertGreaterThanOrEqual(5, $summary['scenario_count']);
        $this->assertContains('provider_intake', array_column($summary['scenarios'], 'key'));
        $this->assertFalse($summary['scenarios'][0]['generates_traffic']);
    }

    public function test_load_readiness_command_outputs_safe_summary(): void
    {
        $this->domain();

        $this->artisan('system:load-readiness')
            ->expectsOutput('Load readiness: READY')
            ->expectsOutput('Load status: READY')
            ->expectsOutput('Stress status: READY')
            ->expectsOutputToContain('Scenarios:')
            ->doesntExpectOutputToContain('load-secret')
            ->assertSuccessful();
    }

    public function test_command_fails_when_load_is_blocked(): void
    {
        config([
            'load-testing.thresholds.queue_pending_warning' => 1,
            'load-testing.thresholds.queue_pending_blocker' => 1,
        ]);
        DB::table('jobs')->insert([
            'queue' => 'inbound-mail',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->artisan('system:load-readiness')
            ->expectsOutput('Load readiness: BLOCKED')
            ->expectsOutputToContain('Blocker: load.queue_backlog')
            ->assertFailed();
    }

    public function test_provider_intake_and_queue_job_review_remains_safe(): void
    {
        $checks = collect(app(ProductionLoadValidationService::class)->report()['checks']);

        $this->assertSame('passed', $checks->firstWhere('name', 'inbound_job_idempotency')['status']);
        $this->assertSame('passed', $checks->firstWhere('name', 'transaction_safety')['status']);
        $this->assertSame('passed', $checks->firstWhere('name', 'intake_key_generation')['status']);
    }

    public function test_operations_load_review_detects_critical_incidents(): void
    {
        Incident::query()->create([
            'uuid' => (string) Str::uuid(),
            'category' => 'queue',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'Queue critical',
            'detected_at' => now(),
        ]);

        $report = app(ProductionLoadValidationService::class)->report();

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('active_alert_review', array_column($report['blockers'], 'name'));
    }

    private function domain(): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => 'load.test',
            'status' => DomainStatus::Active,
            'onboarding_state' => DomainOnboardingState::Active,
            'type' => DomainType::Public,
            'tier' => DomainTier::Free,
            'priority' => 10,
            'health_score' => 100,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'metadata' => ['load' => true],
        ]);
    }
}
