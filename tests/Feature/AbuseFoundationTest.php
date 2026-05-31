<?php

namespace Tests\Feature;

use App\Enums\AbuseEventType;
use App\Enums\AbuseSeverity;
use App\Enums\AbuseStatus;
use App\Models\AbuseEvent;
use App\Services\Abuse\AbuseDecisionService;
use App\Services\Abuse\AbuseLoggerService;
use App\Services\Abuse\AbuseSignalService;
use App\Services\Abuse\RateLimitProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class AbuseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'abuse.enabled' => true,
            'abuse.hash_salt' => 'testing-abuse-salt',
            'abuse.ip_hashing_enabled' => true,
            'abuse.session_hashing_enabled' => true,
            'abuse.user_signal_enabled' => true,
        ]);
    }

    public function test_abuse_event_migration_model_casts_and_helpers_work(): void
    {
        $event = AbuseEvent::query()->create([
            'uuid' => fake()->uuid(),
            'event_type' => AbuseEventType::SuspiciousActivity,
            'severity' => AbuseSeverity::Critical,
            'status' => AbuseStatus::Blocked,
            'metadata' => ['source' => 'test'],
            'occurred_at' => now(),
        ]);

        $this->assertSame(AbuseEventType::SuspiciousActivity, $event->event_type);
        $this->assertSame(['source' => 'test'], $event->metadata);
        $this->assertTrue($event->isBlocked());
        $this->assertFalse($event->isThrottled());
        $this->assertTrue($event->isCritical());
    }

    public function test_signal_service_hashes_ip_session_and_user_agent(): void
    {
        $request = $this->requestWithSignals();
        $signals = app(AbuseSignalService::class)->collect($request);
        $encoded = json_encode($signals);

        $this->assertNotNull($signals['ip_hash']);
        $this->assertNotNull($signals['session_hash']);
        $this->assertNotNull($signals['user_agent_hash']);
        $this->assertStringNotContainsString('203.0.113.25', $encoded);
        $this->assertStringNotContainsString('Sensitive Browser', $encoded);
        $this->assertStringNotContainsString('test-session-id', $encoded);
    }

    public function test_logger_sanitizes_metadata_and_never_stores_raw_signals(): void
    {
        $request = $this->requestWithSignals();

        $event = app(AbuseLoggerService::class)->log(
            AbuseEventType::InboxPolling,
            AbuseSeverity::Medium,
            AbuseStatus::Observed,
            'Safe reason.',
            [
                'count' => 3,
                'payload' => ['private' => 'value'],
                'email' => 'private@example.test',
                'note' => '<b>safe</b>',
            ],
            $request,
            20,
        );
        $encoded = $event->toJson();

        $this->assertSame(['count' => 3, 'note' => 'safe'], $event->metadata);
        $this->assertStringNotContainsString('203.0.113.25', $encoded);
        $this->assertStringNotContainsString('Sensitive Browser', $encoded);
        $this->assertStringNotContainsString('private@example.test', $encoded);
    }

    public function test_rate_limit_profile_service_returns_conservative_defaults(): void
    {
        $profile = app(RateLimitProfileService::class)->for(AbuseEventType::InboxPolling);

        $this->assertSame('inbox_polling', $profile['action']);
        $this->assertSame(30, $profile['per_minute']);
        $this->assertGreaterThan(0, $profile['cooldown_seconds']);
    }

    public function test_decision_service_returns_allow_throttle_block_and_escalate_decisions(): void
    {
        $decisions = app(AbuseDecisionService::class);

        $this->assertSame('observed', $decisions->decide(['risk_score' => 10])['status']);
        $this->assertSame('throttled', $decisions->decide(['risk_score' => 40])['status']);
        $this->assertSame('blocked', $decisions->decide(['risk_score' => 70])['status']);
        $this->assertSame('escalated', $decisions->decide(['risk_score' => 90])['status']);
        $this->assertFalse($decisions->decide(['risk_score' => 70])['allowed']);
    }

    public function test_mailbox_generation_rate_limit_returns_safe_response(): void
    {
        config(['abuse.mailbox_generation_limits.per_minute' => 2]);

        $this->post('/inbox/generate')->assertRedirect(route('inbox.index'));
        $this->post('/inbox/generate')->assertRedirect(route('inbox.index'));
        $this->postJson('/inbox/generate')
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Too many requests. Please wait and try again.']);
    }

    public function test_polling_and_message_detail_rate_limits_work(): void
    {
        config([
            'abuse.polling_limits.per_minute' => 2,
            'abuse.message_detail_limits.per_minute' => 2,
        ]);

        $this->getJson('/inbox/messages')->assertOk();
        $this->getJson('/inbox/messages')->assertOk();
        $this->getJson('/inbox/messages')->assertTooManyRequests();

        $this->getJson('/inbox/messages/missing-message')->assertNotFound();
        $this->getJson('/inbox/messages/missing-message')->assertNotFound();
        $this->getJson('/inbox/messages/missing-message')->assertTooManyRequests();
    }

    public function test_registration_honeypot_logs_privacy_safe_abuse_event(): void
    {
        $this->post('/register', [
            'name' => 'Example User',
            'email' => 'example@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'website' => 'bot-filled-field',
            'form_started_at' => now()->subSeconds(3)->timestamp,
        ])->assertSessionHasErrors('website');

        $event = AbuseEvent::query()
            ->where('event_type', AbuseEventType::RegistrationAttempt)
            ->firstOrFail();

        $this->assertTrue($event->isBlocked());
        $this->assertSame('Registration honeypot triggered.', $event->reason);
        $this->assertStringNotContainsString('bot-filled-field', $event->toJson());
    }

    public function test_failed_login_attempt_logs_observed_event_without_exposing_credentials(): void
    {
        $this->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'private-password',
        ])->assertSessionHasErrors('email');

        $event = AbuseEvent::query()
            ->where('event_type', AbuseEventType::LoginAttempt)
            ->firstOrFail();

        $this->assertSame(AbuseStatus::Observed, $event->status);
        $this->assertStringNotContainsString('missing@example.com', $event->toJson());
        $this->assertStringNotContainsString('private-password', $event->toJson());
    }

    public function test_existing_routes_installer_and_admin_still_behave(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/admin')->assertForbidden();
        $this->assertContains($this->get('/install')->getStatusCode(), [200, 302]);
    }

    private function requestWithSignals(): Request
    {
        $request = Request::create('/inbox/messages', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.25',
            'HTTP_USER_AGENT' => 'Sensitive Browser',
        ]);
        $session = new Store('test', new ArraySessionHandler(120), 'test-session-id');
        $request->setLaravelSession($session);

        return $request;
    }
}
