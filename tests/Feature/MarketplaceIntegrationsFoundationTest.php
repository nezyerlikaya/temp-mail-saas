<?php

namespace Tests\Feature;

use App\Contracts\Integrations\ConnectorContract;
use App\Enums\IntegrationStatus;
use App\Enums\StaffStatus;
use App\Enums\UserIntegrationStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookStatus;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\OutboundWebhook;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffUser;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\Integrations\Connectors\LocalConnector;
use App\Services\Integrations\EventSubscriptionService;
use App\Services\Integrations\IntegrationRegistryService;
use App\Services\Integrations\OutboundWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceIntegrationsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrations_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('integrations'));
        $this->assertTrue(Schema::hasColumns('integrations', [
            'uuid',
            'slug',
            'name',
            'description',
            'category',
            'status',
            'version',
            'metadata',
        ]));
    }

    public function test_user_integrations_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('user_integrations'));
        $this->assertTrue(Schema::hasColumns('user_integrations', [
            'integration_id',
            'user_id',
            'organization_id',
            'status',
            'configuration',
            'connected_at',
            'disconnected_at',
        ]));
    }

    public function test_outbound_webhooks_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('outbound_webhooks'));
        $this->assertTrue(Schema::hasColumns('outbound_webhooks', [
            'uuid',
            'user_id',
            'organization_id',
            'url',
            'status',
            'secret_hash',
            'subscribed_events',
            'last_delivery_at',
        ]));
    }

    public function test_webhook_deliveries_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('webhook_deliveries'));
        $this->assertTrue(Schema::hasColumns('webhook_deliveries', [
            'outbound_webhook_id',
            'event_name',
            'status',
            'response_code',
            'delivered_at',
            'payload_hash',
        ]));
    }

    public function test_registry_service_works(): void
    {
        $integration = app(IntegrationRegistryService::class)->register([
            'slug' => 'local-automation',
            'name' => 'Local Automation',
            'category' => 'automation',
            'status' => IntegrationStatus::Active,
            'version' => '1.0.0',
            'metadata' => [
                'compatibility' => 'v1',
            ],
        ]);

        $this->assertTrue(Str::isUuid($integration->uuid));
        $this->assertTrue($integration->isActive());
        $this->assertSame('v1', app(IntegrationRegistryService::class)->metadata('local-automation')['compatibility']);
        $this->assertTrue(app(IntegrationRegistryService::class)->ensureActive('local-automation')->is($integration));
    }

    public function test_webhook_service_works_and_hashes_secrets(): void
    {
        $user = User::factory()->create();
        $service = app(OutboundWebhookService::class);

        $webhook = $service->createWebhook(
            'https://example.com/webhooks/temp-mail',
            ['mail.received', 'mail.received'],
            'plain-secret',
            user: $user,
        );

        $this->assertTrue($webhook->isActive());
        $this->assertSame(['mail.received'], $webhook->subscribed_events);
        $this->assertNotSame('plain-secret', $webhook->secret_hash);
        $this->assertTrue(Hash::check('plain-secret', $webhook->secret_hash));

        $rawSecretHash = OutboundWebhook::query()->whereKey($webhook->id)->toBase()->value('secret_hash');

        $this->assertNotSame('plain-secret', $rawSecretHash);

        $delivery = $service->queueDelivery($webhook, 'mail.received', ['id' => 123, 'body' => 'not stored']);

        $this->assertSame(WebhookDeliveryStatus::Pending, $delivery->status);
        $this->assertNotNull($delivery->payload_hash);
        $this->assertFalse(Schema::hasColumn('webhook_deliveries', 'payload'));

        $recorded = $service->recordDelivery($delivery, WebhookDeliveryStatus::Delivered, 202);

        $this->assertTrue($recorded->isDelivered());
        $this->assertSame(202, $recorded->response_code);
        $this->assertNotNull($recorded->delivered_at);
    }

    public function test_rotate_secret_replaces_secret_hash(): void
    {
        $service = app(OutboundWebhookService::class);
        $webhook = $service->createWebhook('https://example.com/hook', ['mail.received'], 'old-secret');
        $oldHash = $webhook->secret_hash;

        $newSecret = $service->rotateSecret($webhook);

        $this->assertNotSame($oldHash, $webhook->fresh()->secret_hash);
        $this->assertTrue(Hash::check($newSecret, $webhook->fresh()->secret_hash));
    }

    public function test_event_subscription_resolution_works(): void
    {
        $user = User::factory()->create();
        $webhooks = app(OutboundWebhookService::class);
        $active = $webhooks->createWebhook('https://example.com/a', ['mail.received'], user: $user);
        $webhooks->createWebhook('https://example.com/b', ['billing.updated'], user: $user);
        $webhooks->createWebhook('https://example.com/c', ['mail.received'], status: WebhookStatus::Paused);

        $service = app(EventSubscriptionService::class);

        $resolved = $service->resolveWebhooks('mail.received', user: $user);

        $this->assertCount(1, $resolved);
        $this->assertTrue($resolved->first()->is($active));

        $deliveries = $service->prepareDeliveries('mail.received', ['message' => 'ready'], user: $user);

        $this->assertCount(1, $deliveries);
        $this->assertDatabaseHas('webhook_deliveries', [
            'outbound_webhook_id' => $active->id,
            'event_name' => 'mail.received',
            'status' => WebhookDeliveryStatus::Pending->value,
        ]);
    }

    public function test_connector_contract_implementation_works(): void
    {
        $integration = Integration::query()->create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'local',
            'name' => 'Local Connector',
            'category' => 'developer',
            'status' => IntegrationStatus::Active,
            'metadata' => [],
        ]);
        $user = User::factory()->create();
        $connector = app(LocalConnector::class);

        $this->assertInstanceOf(ConnectorContract::class, $connector);
        $this->assertSame('local', $connector->connectorName());

        $userIntegration = $connector->connect($integration, user: $user, configuration: [
            'label' => 'Internal workflow',
            'secret' => 'do-not-store',
        ]);

        $this->assertTrue($userIntegration->isConnected());
        $this->assertSame('Internal workflow', $userIntegration->configuration['label']);
        $this->assertArrayNotHasKey('secret', $userIntegration->configuration);

        $rawConfiguration = $userIntegration->newQuery()->whereKey($userIntegration->id)->toBase()->value('configuration');

        $this->assertStringNotContainsString('Internal workflow', $rawConfiguration);
        $this->assertSame(UserIntegrationStatus::Disconnected, $connector->disconnect($userIntegration)->status);
    }

    public function test_organization_compatibility_exists(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Example Org',
            'slug' => 'example-org',
            'status' => 'active',
            'owner_user_id' => $owner->id,
            'metadata' => [],
        ]);

        $webhook = app(OutboundWebhookService::class)->createWebhook(
            'https://example.com/org-hook',
            ['organization.updated'],
            organization: $organization,
        );

        $this->assertTrue($organization->outboundWebhooks()->whereKey($webhook->id)->exists());
    }

    public function test_rbac_permissions_are_registered_and_enforced(): void
    {
        $permissions = config('permissions.groups.integrations');

        $this->assertArrayHasKey('integrations.view', $permissions);
        $this->assertArrayHasKey('integrations.manage', $permissions);
        $this->assertArrayHasKey('webhooks.view', $permissions);
        $this->assertArrayHasKey('webhooks.manage', $permissions);

        $staff = $this->staffWithPermissions(['integrations.view']);

        $this->assertTrue(Gate::forUser($staff)->allows('staff-permission', 'integrations.view'));
        $this->assertFalse(Gate::forUser($staff)->allows('staff-permission', 'integrations.manage'));
    }

    private function staffWithPermissions(array $permissions): StaffUser
    {
        $staff = StaffUser::query()->create([
            'name' => 'Integrations Staff',
            'email' => uniqid('staff-', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Integrations Role',
            'slug' => uniqid('integrations-role-', false),
            'is_system' => false,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'group' => str($slug)->before('.')->toString(),
                ],
            );

            $role->permissions()->attach($permission);
        }

        $staff->roles()->attach($role);

        return $staff;
    }
}
