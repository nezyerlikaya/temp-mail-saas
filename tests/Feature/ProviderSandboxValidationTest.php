<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Models\OperationsEvent;
use App\Services\Mail\ProviderRegistryService;
use App\Services\Mail\ProviderSandboxValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProviderSandboxValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail-providers.sandbox.enabled' => true,
            'mail-providers.sandbox.accept_test_signatures' => true,
            'mail-providers.sandbox.payload_logging_enabled' => false,
            'mail-providers.sandbox.observability_enabled' => true,
            'mail-providers.sandbox.allowed_providers' => ['mailgun', 'postmark', 'ses'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.ses.enabled' => true,
            'domains.public_mailbox.default_domain' => 'example.test',
            'domains.public_mailbox.allowed_domains' => ['example.test'],
        ]);
    }

    public function test_sandbox_config_defaults_are_safe(): void
    {
        $this->assertTrue((bool) config('mail-providers.sandbox.enabled'));
        $this->assertTrue((bool) config('mail-providers.sandbox.accept_test_signatures'));
        $this->assertFalse((bool) config('mail-providers.sandbox.payload_logging_enabled'));
        $this->assertContains('mailgun', config('mail-providers.sandbox.allowed_providers'));
    }

    public function test_provider_fixtures_are_safe_and_valid_json(): void
    {
        foreach (File::files(base_path('tests/Fixtures/mail-providers')) as $file) {
            $json = File::get($file->getPathname());
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            $this->assertArrayHasKey('provider', $data);
            $this->assertArrayHasKey('payload', $data);
            $this->assertStringNotContainsString('@gmail.', $json);
            $this->assertStringNotContainsString('@outlook.', $json);
            $this->assertStringNotContainsString('secret', strtolower($json));
        }
    }

    public function test_valid_mailgun_fixture_normalizes_and_reaches_inbox(): void
    {
        $report = app(ProviderSandboxValidationService::class)->validate('mailgun', 'mailgun-valid.json');

        $this->assertSame('passed', $report['status']);
        $this->assertSame('Mailgun sandbox valid', EmailMessage::query()->firstOrFail()->subject);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'sandbox_provider_validated']);
    }

    public function test_invalid_mailgun_fixture_is_rejected(): void
    {
        $report = app(ProviderSandboxValidationService::class)->validate('mailgun', 'mailgun-invalid.json');

        $this->assertSame('passed', $report['status']);
        $this->assertSame(0, InboundMailIntake::query()->count());
        $this->assertDatabaseHas('operations_events', ['event_type' => 'sandbox_signature_rejected']);
    }

    public function test_valid_postmark_fixture_normalizes(): void
    {
        $report = app(ProviderSandboxValidationService::class)->validate('postmark', 'postmark-valid.json');

        $this->assertSame('passed', $report['status']);
        $this->assertSame('Postmark sandbox valid', EmailMessage::query()->firstOrFail()->subject);
    }

    public function test_invalid_postmark_fixture_is_rejected(): void
    {
        $report = app(ProviderSandboxValidationService::class)->validate('postmark', 'postmark-invalid.json');

        $this->assertSame('passed', $report['status']);
        $this->assertSame(0, EmailMessage::query()->count());
    }

    public function test_valid_ses_fixture_normalizes(): void
    {
        $report = app(ProviderSandboxValidationService::class)->validate('ses', 'ses-valid.json');

        $this->assertSame('passed', $report['status']);
        $this->assertSame('SES sandbox valid', EmailMessage::query()->firstOrFail()->subject);
    }

    public function test_invalid_ses_fixture_is_rejected(): void
    {
        $report = app(ProviderSandboxValidationService::class)->validate('ses', 'ses-invalid.json');

        $this->assertSame('passed', $report['status']);
        $this->assertSame(0, EmailMessage::query()->count());
    }

    public function test_expired_signature_simulation_rejects(): void
    {
        $result = app(ProviderSandboxValidationService::class)->simulateExpiredFixture('mailgun');

        $this->assertFalse($result['signature_valid']);
    }

    public function test_sandbox_duplicate_observability_event_is_created(): void
    {
        $service = app(ProviderSandboxValidationService::class);

        $service->validate('mailgun', 'mailgun-valid.json');
        $service->validate('mailgun', 'mailgun-valid.json');

        $this->assertDatabaseHas('operations_events', ['event_type' => 'sandbox_duplicate_detected']);
        $this->assertSame(1, InboundMailIntake::query()->count());
        $this->assertSame(1, EmailMessage::query()->count());
    }

    public function test_provider_normalization_contains_required_storage_fields(): void
    {
        $service = app(ProviderSandboxValidationService::class);
        $providers = app(ProviderRegistryService::class);
        $reflection = new \ReflectionClass($service);
        $fixtureData = $reflection->getMethod('fixtureData');
        $preparePayload = $reflection->getMethod('preparePayload');
        $applyKey = $reflection->getMethod('applySandboxSigningKey');

        foreach (['mailgun', 'postmark', 'ses'] as $provider) {
            $applyKey->invoke($service, $provider);
            $data = $fixtureData->invoke($service, "{$provider}-valid.json");
            $payload = $preparePayload->invoke($service, $provider, $data['payload'], 'valid');
            $normalized = $providers->resolve($provider)->normalizePayload($payload);

            foreach (['mailbox_address', 'from_email', 'from_name', 'subject', 'text_body', 'html_body', 'recipients', 'attachments', 'received_at', 'intake_source', 'provider_id', 'intake_key'] as $field) {
                $this->assertArrayHasKey($field, $normalized);
            }
        }
    }

    public function test_sandbox_command_works_and_does_not_leak_payload_or_secrets(): void
    {
        $this->artisan('mail:provider-sandbox-check --provider=mailgun --fixture=mailgun-valid.json')
            ->expectsOutput('Provider sandbox status: PASSED')
            ->expectsOutput('Passed: 1')
            ->expectsOutput('Blockers: 0')
            ->doesntExpectOutputToContain('sandbox-mailgun-signing-key')
            ->doesntExpectOutputToContain('Mailgun sandbox text body.')
            ->assertSuccessful();
    }

    public function test_sandbox_command_returns_failure_for_blockers(): void
    {
        config(['mail-providers.sandbox.allowed_providers' => ['postmark']]);

        $this->artisan('mail:provider-sandbox-check --provider=mailgun --fixture=mailgun-valid.json')
            ->expectsOutput('Provider sandbox status: BLOCKER')
            ->assertFailed();
    }
}
