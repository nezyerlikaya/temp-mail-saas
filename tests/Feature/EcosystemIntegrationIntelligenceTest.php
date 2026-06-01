<?php

namespace Tests\Feature;

use App\Enums\IntegrationStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Models\Integration;
use App\Models\OperationsEvent;
use App\Services\Integrations\ConnectorHealthService;
use App\Services\Integrations\EcosystemCertificationService;
use App\Services\Integrations\IntegrationEcosystemService;
use App\Services\Integrations\IntegrationRegistryService;
use App\Services\Integrations\OutboundWebhookService;
use App\Services\Integrations\PlatformDependencyService;
use App\Services\Integrations\WebhookEcosystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcosystemIntegrationIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_ecosystem_review_reports_healthy_state(): void
    {
        $this->integration();

        $report = app(IntegrationEcosystemService::class)->review();

        $this->assertSame('healthy', $report['state']);
        $this->assertSame(1, $report['registered_integrations']);
        $this->assertSame(1, $report['active_integrations']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'ecosystem_review_completed']);
    }

    public function test_integration_ecosystem_warns_without_registry_coverage(): void
    {
        $report = app(IntegrationEcosystemService::class)->review();

        $this->assertSame('attention', $report['state']);
        $this->assertContains('ecosystem_coverage', array_column($report['warnings'], 'name'));
    }

    public function test_connector_health_reviews_contracts_and_lifecycle(): void
    {
        $integration = $this->integration();
        $connector = app(config('integrations.connectors.local'));
        $connection = $connector->connect($integration, configuration: ['label' => 'safe', 'secret' => 'hidden']);
        $connector->disconnect($connection);

        $report = app(ConnectorHealthService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertSame(1, $report['connector_count']);
        $this->assertSame(1, $report['inactive_count']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'connector_review_completed']);
    }

    public function test_connector_health_blocks_missing_connector_class(): void
    {
        config(['integrations.connectors.missing' => 'App\\Missing\\Connector']);

        $report = app(ConnectorHealthService::class)->review();

        $this->assertSame('blocked', $report['status']);
        $this->assertContains('connector_configuration', array_column($report['blockers'], 'name'));
    }

    public function test_webhook_ecosystem_review_uses_hash_only_delivery_storage(): void
    {
        $webhooks = app(OutboundWebhookService::class);
        $webhook = $webhooks->createWebhook('https://example.com/hook', ['mail.received'], 'plain-secret');
        $delivery = $webhooks->queueDelivery($webhook, 'mail.received', ['body' => 'secret-payload']);
        $webhooks->recordDelivery($delivery, WebhookDeliveryStatus::Failed, 500);

        $report = app(WebhookEcosystemService::class)->review();

        $this->assertSame('warning', $report['status']);
        $this->assertSame(1, $report['webhook_count']);
        $this->assertSame(1, $report['failed_delivery_count']);
        $this->assertTrue(Schema::hasColumn('webhook_deliveries', 'payload_hash'));
        $this->assertFalse(Schema::hasColumn('webhook_deliveries', 'payload'));
        $this->assertDatabaseHas('operations_events', ['event_type' => 'webhook_review_completed']);
    }

    public function test_platform_dependency_service_reports_ready_state(): void
    {
        $report = app(PlatformDependencyService::class)->review();

        $this->assertSame('ready', $report['status']);
        $this->assertSame([], $report['blockers']);
    }

    public function test_ecosystem_certification_aggregates_reviews_and_events(): void
    {
        $this->integration();

        $report = app(EcosystemCertificationService::class)->report();

        $this->assertSame('certified', $report['status']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'ecosystem_review_started']);
        $this->assertDatabaseHas('operations_events', ['event_type' => 'ecosystem_certified']);
    }

    public function test_ecosystem_command_outputs_safe_summary(): void
    {
        $this->integration();

        $this->artisan('system:ecosystem-status')
            ->expectsOutput('Ecosystem readiness: CERTIFIED')
            ->expectsOutput('Integration ecosystem: HEALTHY')
            ->doesntExpectOutputToContain('plain-secret')
            ->doesntExpectOutputToContain('secret-payload')
            ->assertSuccessful();
    }

    public function test_ecosystem_reports_do_not_expose_connector_or_webhook_secrets(): void
    {
        $integration = $this->integration();
        app(config('integrations.connectors.local'))->connect($integration, configuration: [
            'label' => 'Internal',
            'secret' => 'connector-secret',
        ]);
        $webhook = app(OutboundWebhookService::class)->createWebhook('https://example.com/private-hook', ['mail.received'], 'webhook-secret');
        app(OutboundWebhookService::class)->queueDelivery($webhook, 'mail.received', ['payload' => 'raw-webhook-payload']);

        $encoded = json_encode(app(EcosystemCertificationService::class)->report(), JSON_THROW_ON_ERROR);
        $events = OperationsEvent::query()->where('source', 'ecosystem-intelligence')->get()->toJson();

        $this->assertStringNotContainsString('connector-secret', $encoded);
        $this->assertStringNotContainsString('webhook-secret', $encoded);
        $this->assertStringNotContainsString('raw-webhook-payload', $encoded);
        $this->assertStringNotContainsString('private-hook', $encoded);
        $this->assertStringNotContainsString('connector-secret', $events);
        $this->assertStringNotContainsString('webhook-secret', $events);
    }

    private function integration(): Integration
    {
        return app(IntegrationRegistryService::class)->register([
            'slug' => 'local-automation-'.Str::random(6),
            'name' => 'Local Automation',
            'category' => 'automation',
            'status' => IntegrationStatus::Active,
            'version' => '1.0.0',
            'metadata' => ['compatibility' => 'v1'],
        ]);
    }
}
