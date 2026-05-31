<?php

namespace Tests\Feature;

use App\Models\ProviderActivationAudit;
use App\Services\Mail\ProviderActivationService;
use App\Services\Mail\ProviderSafetyCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProviderActivationReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $installerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerPath = storage_path('framework/testing/provider-activation');
        File::deleteDirectory($this->installerPath);
        File::ensureDirectoryExists($this->installerPath);
        File::put($this->installerPath.'/.env', 'APP_KEY=base64:'.base64_encode(str_repeat('p', 32)).PHP_EOL);
        File::put($this->installerPath.'/install.lock', '{}');

        config([
            'installer.env_path' => $this->installerPath.'/.env',
            'installer.lock_path' => $this->installerPath.'/install.lock',
            'app.key' => 'base64:'.base64_encode(str_repeat('p', 32)),
            'mail-providers.activation.states.mailgun' => 'staging',
            'mail-providers.activation.states.postmark' => 'inactive',
            'mail-providers.activation.states.ses' => 'inactive',
            'mail-providers.activation.readiness.providers' => ['mailgun', 'postmark', 'ses'],
            'mail-providers.providers.mailgun.enabled' => true,
            'mail-providers.providers.mailgun.signing_key' => 'activation-mailgun-key',
            'mail-providers.providers.postmark.enabled' => true,
            'mail-providers.providers.postmark.signing_key' => 'activation-postmark-key',
            'mail-providers.providers.ses.enabled' => true,
            'mail-providers.providers.ses.signing_key' => 'activation-ses-key',
            'mail-providers.staging.allowed_domains' => ['example.test'],
            'domains.public_mailbox.default_domain' => 'example.test',
            'domains.public_mailbox.allowed_domains' => ['example.test'],
            'inbound.queue.name' => 'inbound-mail',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerPath);

        parent::tearDown();
    }

    public function test_provider_activation_states_are_configured(): void
    {
        $states = app(ProviderActivationService::class)->states();

        $this->assertSame('staging', $states['mailgun']);
        $this->assertSame('inactive', $states['postmark']);
        $this->assertContains('active', config('mail-providers.activation.allowed_states'));
    }

    public function test_activation_audit_model_and_migration_work(): void
    {
        $audit = ProviderActivationAudit::query()->create([
            'provider' => 'mailgun',
            'previous_state' => 'staging',
            'new_state' => 'ready',
            'reason' => 'Validated in staging',
            'performed_by' => 'release-manager',
            'metadata' => ['safe' => true],
        ]);

        $this->assertDatabaseHas('provider_activation_audits', [
            'id' => $audit->id,
            'provider' => 'mailgun',
            'new_state' => 'ready',
        ]);
        $this->assertSame(['safe' => true], $audit->fresh()->metadata);
    }

    public function test_safety_checks_report_readiness(): void
    {
        $report = app(ProviderSafetyCheckService::class)->report('mailgun');
        $names = array_column($report['checks'], 'name');

        $this->assertSame([], $report['blockers']);
        $this->assertContains('mailgun_activation_state_valid', $names);
        $this->assertContains('mailgun_staging_validation_passed', $names);
        $this->assertContains('mailgun_webhook_readiness_passed', $names);
        $this->assertContains('mailgun_queue_readiness_passed', $names);
        $this->assertContains('mailgun_installer_readiness_passed', $names);
        $this->assertContains('mailgun_idempotency_ready', $names);
    }

    public function test_safety_checks_warn_when_signing_configuration_missing(): void
    {
        config(['mail-providers.providers.mailgun.signing_key' => null]);

        $report = app(ProviderSafetyCheckService::class)->report('mailgun');

        $this->assertContains('mailgun_signing_configuration_present', array_column($report['warnings'], 'name'));
    }

    public function test_activation_transition_to_ready_creates_audit_and_event(): void
    {
        $audit = app(ProviderActivationService::class)->transition('mailgun', 'ready', 'Staging passed', 'release-manager', [
            'ticket' => 'SAFE-1',
            'secret_token' => 'hidden',
        ]);

        $this->assertSame('staging', $audit->previous_state);
        $this->assertSame('ready', $audit->new_state);
        $this->assertSame(['ticket' => 'SAFE-1'], $audit->metadata);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'provider_activation_requested']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'provider_activation_ready']);
    }

    public function test_activation_transition_to_active_and_suspended_records_events(): void
    {
        app(ProviderActivationService::class)->transition('mailgun', 'active', 'Activate provider');
        app(ProviderActivationService::class)->transition('mailgun', 'suspended', 'Suspend provider');

        $this->assertDatabaseHas('operations_events', ['event_type' => 'provider_activation_completed']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'provider_activation_suspended']);
        $this->assertSame(2, ProviderActivationAudit::query()->count());
    }

    public function test_activation_blocks_invalid_state(): void
    {
        $this->expectException(ValidationException::class);

        app(ProviderActivationService::class)->transition('mailgun', 'not-a-state');
    }

    public function test_activation_blocks_when_installer_not_ready(): void
    {
        File::delete($this->installerPath.'/install.lock');

        $this->expectException(ValidationException::class);

        app(ProviderActivationService::class)->transition('mailgun', 'ready');
    }

    public function test_activation_status_command_outputs_safe_summary(): void
    {
        $this->artisan('provider:activation-status --provider=mailgun')
            ->expectsOutput('Provider activation status: READY')
            ->expectsOutput('Provider mailgun: staging')
            ->expectsOutputToContain('Blockers:')
            ->expectsOutputToContain('Warnings:')
            ->doesntExpectOutputToContain('activation-mailgun-key')
            ->assertSuccessful();
    }

    public function test_activation_status_command_fails_when_blocked(): void
    {
        File::delete($this->installerPath.'/install.lock');

        $this->artisan('provider:activation-status --provider=mailgun')
            ->expectsOutput('Provider activation status: BLOCKED')
            ->expectsOutputToContain('Blocker: mailgun_installer_readiness_passed')
            ->assertFailed();
    }

    public function test_queue_compatibility_check_remains_present(): void
    {
        $report = app(ProviderActivationService::class)->readiness('mailgun');

        $this->assertContains('mailgun_queue_readiness_passed', array_column($report['checks'], 'name'));
        $this->assertContains('mailgun_idempotency_ready', array_column($report['checks'], 'name'));
    }
}
