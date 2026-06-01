<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Governance\GovernanceCertificationService;
use App\Services\Governance\GovernanceIntelligenceService;
use App\Services\Governance\OperationalMaturityService;
use App\Services\Governance\PlatformRiskService;
use App\Services\Governance\StrategicOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformGovernanceStrategicOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_governance_intelligence_reports_healthy_state_and_event(): void
    {
        $report = app(GovernanceIntelligenceService::class)->review();

        $this->assertSame('healthy', $report['state']);
        $this->assertSame('ready', $report['status']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'governance_review_completed']);
    }

    public function test_governance_intelligence_can_report_attention_and_risk(): void
    {
        config(['governance.governance.policy_ready' => false]);
        $this->assertSame('attention', app(GovernanceIntelligenceService::class)->review()['state']);

        config(['governance.governance.platform_ready' => false]);
        $this->assertSame('risk', app(GovernanceIntelligenceService::class)->review()['state']);
    }

    public function test_strategic_operations_review_can_warn_and_block(): void
    {
        config(['governance.strategic_operations.maintenance_ready' => false]);
        $this->assertSame('warning', app(StrategicOperationsService::class)->review()['status']);

        config(['governance.strategic_operations.incident_readiness' => false]);
        $this->assertSame('blocked', app(StrategicOperationsService::class)->review()['status']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'strategic_operations_review_completed']);
    }

    public function test_platform_risk_review_classifies_levels(): void
    {
        config([
            'governance.risk.dependency_risk' => 'high',
            'governance.risk.sustainability_risk' => 'critical',
        ]);

        $report = app(PlatformRiskService::class)->review();

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('critical', array_column($report['critical'], 'level'));
        $this->assertContains('high', array_column($report['high'], 'level'));
    }

    public function test_operational_maturity_review_can_warn_and_block(): void
    {
        config(['governance.maturity.documentation' => 'developing']);
        $this->assertSame('warning', app(OperationalMaturityService::class)->review()['status']);

        config(['governance.maturity.testing' => 'unknown']);
        $this->assertSame('blocked', app(OperationalMaturityService::class)->review()['status']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'maturity_review_completed']);
    }

    public function test_governance_certification_reports_certified_and_events(): void
    {
        $report = app(GovernanceCertificationService::class)->report();

        $this->assertSame('certified', $report['status']);
        $this->assertSame([], $report['blockers']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'governance_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'governance_certified']);
    }

    public function test_governance_certification_blocks_on_critical_risk(): void
    {
        config(['governance.risk.operational_risk' => 'critical']);

        $report = app(GovernanceCertificationService::class)->report();

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('risk_readiness', array_column($report['blockers'], 'name'));
    }

    public function test_governance_command_outputs_safe_summary(): void
    {
        $this->artisan('system:governance-status')
            ->expectsOutput('Governance readiness: CERTIFIED')
            ->expectsOutput('Governance state: HEALTHY')
            ->expectsOutput('Operational maturity: READY')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();
    }

    public function test_governance_reports_and_events_do_not_expose_sensitive_data(): void
    {
        $encoded = json_encode(app(GovernanceCertificationService::class)->report(), JSON_THROW_ON_ERROR);
        $events = OperationsEvent::query()->where('source', 'governance')->get()->toJson();

        $this->assertStringNotContainsString('private@example.com', $encoded);
        $this->assertStringNotContainsString('mailbox@example.test', $encoded);
        $this->assertStringNotContainsString('raw-payload', $encoded);
        $this->assertStringNotContainsString('provider-secret', $encoded);
        $this->assertStringNotContainsString('provider-secret', $events);
    }
}
