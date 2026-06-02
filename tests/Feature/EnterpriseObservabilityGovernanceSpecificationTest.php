<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\EnterpriseObservabilityGovernanceSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnterpriseObservabilityGovernanceSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_observability_config_is_readable(): void
    {
        $this->assertIsArray(config('enterprise-observability.audit_governance'));
        $this->assertIsArray(config('enterprise-observability.observability_domains'));
        $this->assertIsArray(config('enterprise-observability.operational_governance'));
        $this->assertIsArray(config('enterprise-observability.monitoring_governance'));
        $this->assertSame('v1.1 Enterprise Reporting, Evidence Export & Dashboard Specification', config('enterprise-observability.step74.recommended_next_phase'));
    }

    public function test_specification_service_reports_required_scores(): void
    {
        $report = app(EnterpriseObservabilityGovernanceSpecificationService::class)->report();

        $this->assertSame(56, $report['scores']['audit_governance_readiness']);
        $this->assertSame(52, $report['scores']['observability_readiness']);
        $this->assertSame(54, $report['scores']['operational_governance_readiness']);
        $this->assertSame(48, $report['scores']['compliance_evidence_readiness']);
        $this->assertSame(51, $report['scores']['monitoring_governance_readiness']);
        $this->assertSame(40, $report['scores']['siem_readiness']);
    }

    public function test_audit_governance_event_classification_and_evidence_are_specified(): void
    {
        $report = app(EnterpriseObservabilityGovernanceSpecificationService::class)->report();

        $this->assertArrayHasKey('identity_audit', $report['audit_governance']);
        $this->assertArrayHasKey('organization_audit', $report['audit_governance']);
        $this->assertContains('authorization_events', $report['audit_event_classification']);
        $this->assertContains('integration_events', $report['audit_event_classification']);
        $this->assertArrayHasKey('access_review_evidence', $report['audit_evidence_model']);
        $this->assertArrayHasKey('retention_evidence', $report['audit_evidence_model']);
    }

    public function test_observability_operational_incident_monitoring_and_risk_models_are_specified(): void
    {
        $report = app(EnterpriseObservabilityGovernanceSpecificationService::class)->report();

        $this->assertArrayHasKey('security_signals', $report['observability_domains']);
        $this->assertArrayHasKey('service_ownership', $report['operational_governance']);
        $this->assertContains('security_incident', $report['incident_observability']['types']);
        $this->assertContains('verified', $report['incident_observability']['lifecycle']);
        $this->assertArrayHasKey('queue_health', $report['monitoring_governance']);
        $this->assertArrayHasKey('security_risk', $report['operational_risks']);
        $this->assertArrayHasKey('soc2_evidence', $report['compliance_evidence']);
        $this->assertContains('risk_dashboard', $report['dashboard_readiness']);
        $this->assertArrayHasKey('siem_export', $report['external_integration_readiness']);
        $this->assertContains('audit_export', $report['observability_audit_events']);
    }

    public function test_observability_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-audit-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-observability-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-operational-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-compliance-evidence-readiness.md'));
        $this->assertTrue(File::exists($base.'/v1.1-monitoring-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-siem-readiness-analysis.md'));
        $this->assertStringContainsString('Audit Governance Scope', File::get($base.'/v1.1-audit-governance-specification.md'));
        $this->assertStringContainsString('Observability Domains', File::get($base.'/v1.1-observability-specification.md'));
        $this->assertStringContainsString('Operational Accountability', File::get($base.'/v1.1-operational-governance-specification.md'));
        $this->assertStringContainsString('Evidence Families', File::get($base.'/v1.1-compliance-evidence-readiness.md'));
        $this->assertStringContainsString('Monitoring Domains', File::get($base.'/v1.1-monitoring-governance-specification.md'));
        $this->assertStringContainsString('Future SIEM Goals', File::get($base.'/v1.1-siem-readiness-analysis.md'));
    }

    public function test_enterprise_observability_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:enterprise-observability-status')
            ->expectsOutput('v1.1 enterprise observability governance summary')
            ->expectsOutput('Audit Governance Readiness Score: 56')
            ->expectsOutput('Observability Readiness Score: 52')
            ->expectsOutput('Operational Governance Readiness Score: 54')
            ->expectsOutput('Compliance Evidence Readiness Score: 48')
            ->expectsOutput('Monitoring Governance Readiness Score: 51')
            ->expectsOutput('SIEM Readiness Score: 40')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step73_does_not_add_observability_siem_evidence_or_monitoring_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'enterprise_observability')
                || str_contains($name, 'siem')
                || str_contains($name, 'audit_evidence')
                || str_contains($name, 'compliance_evidence')
                || str_contains($name, 'observability'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_or_secret_data(): void
    {
        $encoded = json_encode(app(EnterpriseObservabilityGovernanceSpecificationService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('plain_text_password', $encoded);
        $this->assertStringNotContainsString('provider_secret_value', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
