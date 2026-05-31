<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\ApiKeyStatus;
use App\Models\ApiUsageLog;
use App\Models\User;
use App\Services\Api\ApiAuthService;
use App\Services\Api\ApiKeyService;
use App\Services\Api\ApiRateLimitService;
use App\Services\Api\ApiUsageLoggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiAccessFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_and_usage_migrations_work(): void
    {
        $this->assertTrue(Schema::hasTable('api_keys'));
        $this->assertTrue(Schema::hasColumns('api_keys', [
            'uuid',
            'user_id',
            'name',
            'key_prefix',
            'key_hash',
            'status',
            'last_used_at',
            'expires_at',
            'revoked_at',
            'metadata',
        ]));
        $this->assertTrue(Schema::hasTable('api_usage_logs'));
        $this->assertTrue(Schema::hasColumns('api_usage_logs', [
            'api_key_id',
            'endpoint',
            'method',
            'response_status',
            'request_count',
            'occurred_at',
        ]));
    }

    public function test_key_generation_hashing_and_verification_work(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $result = app(ApiKeyService::class)->create($user, 'Testing key', metadata: [
            'label' => 'safe',
            'secret_note' => 'hidden',
        ]);
        $apiKey = $result['api_key'];
        $rawKey = $result['plain_text_key'];

        $this->assertStringStartsWith('tm_', $rawKey);
        $this->assertNotSame($rawKey, $apiKey->key_hash);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $rawKey]);
        $this->assertSame(['label' => 'safe'], $apiKey->metadata);

        $verified = app(ApiKeyService::class)->verify($rawKey);

        $this->assertTrue($verified->is($apiKey));
        $this->assertTrue($verified->fresh()->last_used_at !== null);
        $this->assertTrue($verified->isActive());
    }

    public function test_revoked_and_expired_keys_are_rejected(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $service = app(ApiKeyService::class);
        $revoked = $service->create($user, 'Revoked');
        $service->revoke($revoked['api_key']);

        $expired = $service->create($user, 'Expired', now()->subMinute());
        $expired['api_key']->forceFill(['status' => ApiKeyStatus::Expired])->save();

        $this->assertNull($service->verify($revoked['plain_text_key']));
        $this->assertNull($service->verify($expired['plain_text_key']));
        $this->assertTrue($revoked['api_key']->fresh()->isRevoked());
        $this->assertTrue($expired['api_key']->fresh()->isExpired());
    }

    public function test_api_auth_service_resolves_bearer_token(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $created = app(ApiKeyService::class)->create($user, 'Bearer');
        $request = Request::create('/api/v1/ping', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$created['plain_text_key'],
        ]);

        $result = app(ApiAuthService::class)->authenticate($request);

        $this->assertTrue($result['authenticated']);
        $this->assertTrue($result['user']->is($user));
    }

    public function test_middleware_validates_keys_and_logs_usage(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $created = app(ApiKeyService::class)->create($user, 'Route');

        $this->getJson('/api/v1/ping')->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$created['plain_text_key'])
            ->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'scope' => 'api-foundation',
            ]);

        $this->assertDatabaseHas('api_usage_logs', [
            'endpoint' => '/api/v1/ping',
            'method' => 'GET',
            'response_status' => 200,
        ]);
        $this->assertSame(2, ApiUsageLog::query()->count());
    }

    public function test_free_plan_api_key_is_authenticated_but_access_is_disabled(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Free]);
        $created = app(ApiKeyService::class)->create($user, 'Free');

        $this->withHeader('Authorization', 'Bearer '.$created['plain_text_key'])
            ->getJson('/api/v1/ping')
            ->assertForbidden()
            ->assertJson(['message' => 'API access is not enabled for this account.']);
    }

    public function test_rate_limit_service_is_plan_aware_and_feature_gate_compatible(): void
    {
        $free = User::factory()->create(['account_tier' => AccountTier::Free]);
        $premium = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $limits = app(ApiRateLimitService::class);

        $this->assertFalse($limits->limitFor($free)['enabled']);
        $this->assertTrue($limits->limitFor($premium)['enabled']);
        $this->assertSame(300, $limits->limitFor($premium)['per_minute']);
    }

    public function test_usage_logger_avoids_payload_storage(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $created = app(ApiKeyService::class)->create($user, 'Usage');
        $request = Request::create('/api/v1/ping', 'POST', [
            'payload' => 'private',
        ]);

        $log = app(ApiUsageLoggerService::class)->log($created['api_key'], $request, 202);

        $this->assertSame('/api/v1/ping', $log->endpoint);
        $this->assertStringNotContainsString('private', $log->toJson());
    }

    public function test_key_rotation_revokes_old_key_and_returns_new_raw_key_once(): void
    {
        $user = User::factory()->create(['account_tier' => AccountTier::Premium]);
        $created = app(ApiKeyService::class)->create($user, 'Rotate');
        $rotated = app(ApiKeyService::class)->rotate($created['api_key']);

        $this->assertTrue($created['api_key']->fresh()->isRevoked());
        $this->assertNull(app(ApiKeyService::class)->verify($created['plain_text_key']));
        $this->assertNotSame($created['plain_text_key'], $rotated['plain_text_key']);
        $this->assertTrue(app(ApiKeyService::class)->verify($rotated['plain_text_key'])->is($rotated['api_key']));
    }

    public function test_existing_routes_still_work(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/inbox')->assertOk();
        $this->assertContains($this->get('/install')->getStatusCode(), [200, 302]);
        $this->get('/admin')->assertForbidden();
    }
}
