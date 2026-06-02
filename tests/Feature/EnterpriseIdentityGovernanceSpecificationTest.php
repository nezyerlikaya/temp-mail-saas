<?php

namespace Tests\Feature;

use App\Models\OperationsEvent;
use App\Services\Roadmap\EnterpriseIdentityGovernanceSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnterpriseIdentityGovernanceSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_identity_governance_config_is_readable(): void
    {
        $this->assertIsArray(config('enterprise-identity-governance.identity_sources'));
        $this->assertIsArray(config('enterprise-identity-governance.sso'));
        $this->assertIsArray(config('enterprise-identity-governance.federation'));
        $this->assertIsArray(config('enterprise-identity-governance.identity_risks'));
        $this->assertSame('v1.1 Enterprise Provisioning & Lifecycle Control Specification', config('enterprise-identity-governance.step71.recommended_next_phase'));
    }

    public function test_specification_service_reports_required_scores(): void
    {
        $report = app(EnterpriseIdentityGovernanceSpecificationService::class)->report();

        $this->assertSame(52, $report['scores']['identity_governance_readiness']);
        $this->assertSame(44, $report['scores']['enterprise_sso_readiness']);
        $this->assertSame(46, $report['scores']['federation_readiness']);
        $this->assertSame(48, $report['scores']['mfa_governance_readiness']);
        $this->assertSame(50, $report['scores']['session_governance_readiness']);
        $this->assertSame(42, $report['scores']['device_governance_readiness']);
    }

    public function test_identity_sso_federation_and_lifecycle_domains_are_specified(): void
    {
        $report = app(EnterpriseIdentityGovernanceSpecificationService::class)->report();

        $this->assertArrayHasKey('local_identity', $report['identity_sources']);
        $this->assertContains('saml_2_0', $report['sso']['protocols']);
        $this->assertContains('okta', $report['sso']['providers']);
        $this->assertArrayHasKey('claim_mapping', $report['federation']);
        $this->assertContains('pending_activation', $report['identity_lifecycle']['states']);
        $this->assertContains('disabled_to_archived', $report['identity_lifecycle']['transitions']);
    }

    public function test_access_mfa_session_device_risk_and_provisioning_are_specified(): void
    {
        $report = app(EnterpriseIdentityGovernanceSpecificationService::class)->report();

        $this->assertContains('emergency_access', $report['access_governance']['domains']);
        $this->assertArrayHasKey('risk_based_mfa', $report['mfa_governance']);
        $this->assertArrayHasKey('session_revocation', $report['session_governance']);
        $this->assertArrayHasKey('risky_device', $report['device_governance']);
        $this->assertArrayHasKey('api_identity_risk', $report['identity_risks']);
        $this->assertArrayHasKey('scim_readiness', $report['provisioning']);
    }

    public function test_identity_documents_are_readable(): void
    {
        $base = base_path('docs/planning');

        $this->assertTrue(File::exists($base.'/v1.1-identity-governance-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-enterprise-sso-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-federation-model-specification.md'));
        $this->assertTrue(File::exists($base.'/v1.1-session-device-governance.md'));
        $this->assertTrue(File::exists($base.'/v1.1-identity-risk-model.md'));
        $this->assertStringContainsString('Identity Sources', File::get($base.'/v1.1-identity-governance-specification.md'));
        $this->assertStringContainsString('SAML 2.0', File::get($base.'/v1.1-enterprise-sso-specification.md'));
        $this->assertStringContainsString('Trust Relationships', File::get($base.'/v1.1-federation-model-specification.md'));
        $this->assertStringContainsString('Device Governance', File::get($base.'/v1.1-session-device-governance.md'));
        $this->assertStringContainsString('Credential Risk', File::get($base.'/v1.1-identity-risk-model.md'));
    }

    public function test_enterprise_identity_command_outputs_safe_summary_without_writing_data_or_queue_jobs(): void
    {
        Queue::fake();
        $before = OperationsEvent::query()->count();

        $this->artisan('system:enterprise-identity-status')
            ->expectsOutput('v1.1 enterprise identity governance summary')
            ->expectsOutput('Identity Governance Readiness Score: 52')
            ->expectsOutput('Enterprise SSO Readiness Score: 44')
            ->expectsOutput('Federation Readiness Score: 46')
            ->expectsOutput('MFA Governance Readiness Score: 48')
            ->expectsOutput('Session Governance Readiness Score: 50')
            ->expectsOutput('Device Governance Readiness Score: 42')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('token')
            ->assertSuccessful();

        $this->assertSame($before, OperationsEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_step70_does_not_add_sso_mfa_identity_provider_or_tenant_migrations(): void
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->filter(fn (string $name): bool => str_contains($name, 'sso')
                || str_contains($name, 'saml')
                || str_contains($name, 'oidc')
                || str_contains($name, 'mfa')
                || str_contains($name, 'identity_provider')
                || str_contains($name, 'tenant'))
            ->values()
            ->all();

        $this->assertSame([], $migrationFiles);
    }

    public function test_report_does_not_expose_personal_or_secret_data(): void
    {
        $encoded = json_encode(app(EnterpriseIdentityGovernanceSpecificationService::class)->report(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('plain_text_password', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('tm_private_api_key', $encoded);
        $this->assertStringNotContainsString('staff@example.com', $encoded);
    }
}
