<?php

namespace Tests\Feature;

use App\Contracts\Mail\InboundProviderContract;
use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Enums\InboundIntakeStatus;
use App\Jobs\ProcessInboundMailIntake;
use App\Models\Domain;
use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Models\OperationsEvent;
use App\Services\Domain\DomainPoolService;
use App\Services\Mail\InboundMailIntakeService;
use App\Services\Mail\ProviderRegistryService;
use App\Services\Mail\Providers\MailgunInboundProvider;
use App\Services\Mail\Providers\PostmarkInboundProvider;
use App\Services\Mail\Providers\SesInboundProvider;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderIntegrationReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail-providers.providers.mailgun.signing_key' => 'mailgun-secret',
            'mail-providers.providers.postmark.signing_key' => 'postmark-secret',
            'mail-providers.providers.ses.signing_key' => 'ses-secret',
            'mail-providers.signature_tolerance_seconds' => 300,
            'domains.public_mailbox.default_domain' => 'fallback.test',
            'domains.public_mailbox.allowed_domains' => ['fallback.test'],
            'features-gates.plans.free.allowed_domains' => ['fallback.test'],
        ]);
    }

    public function test_provider_registry_resolves_provider_metadata_and_health(): void
    {
        $registry = app(ProviderRegistryService::class);

        $this->assertContains('mailgun', $registry->providers());
        $this->assertInstanceOf(InboundProviderContract::class, $registry->resolve('mailgun'));
        $this->assertInstanceOf(SesInboundProvider::class, $registry->resolve('amazon_ses'));
        $this->assertTrue($registry->health('mailgun')['has_signing_key']);
        $this->assertFalse($registry->metadata('mailgun')['live_api']);
    }

    public function test_mailgun_signature_validation_and_normalization_work(): void
    {
        $timestamp = (string) time();
        $token = 'token-123';
        $payload = [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'mailgun-secret'),
            'recipient' => 'demo@fallback.test',
            'sender' => 'sender@example.net',
            'subject' => 'Mailgun hello',
            'body-plain' => 'Plain',
            'Message-Id' => 'mg-1',
        ];
        $provider = app(MailgunInboundProvider::class);

        $this->assertTrue($provider->verifySignature([], $payload));
        $this->assertSame('demo@fallback.test', $provider->normalizePayload($payload)['mailbox_address']);

        $payload['signature'] = 'bad';

        $this->assertFalse($provider->verifySignature([], $payload));
    }

    public function test_provider_expired_timestamps_are_rejected(): void
    {
        $timestamp = (string) (time() - 999);
        $token = 'token-123';

        $this->assertFalse(app(MailgunInboundProvider::class)->verifySignature([], [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'mailgun-secret'),
        ]));

        $this->assertFalse(app(PostmarkInboundProvider::class)->verifySignature([
            'X-Postmark-Webhook-Token' => 'postmark-secret',
            'X-Postmark-Timestamp' => $timestamp,
        ], []));

        $this->assertFalse(app(SesInboundProvider::class)->verifySignature([], [
            'MessageId' => 'ses-1',
            'Timestamp' => now()->subMinutes(10)->toIso8601String(),
            'Signature' => hash_hmac('sha256', 'ses-1|'.now()->subMinutes(10)->toIso8601String(), 'ses-secret'),
        ]));
    }

    public function test_postmark_and_ses_foundations_verify_and_normalize_payloads(): void
    {
        $postmark = app(PostmarkInboundProvider::class);
        $this->assertTrue($postmark->verifySignature([
            'X-Postmark-Webhook-Token' => 'postmark-secret',
            'X-Postmark-Timestamp' => (string) time(),
        ], []));
        $this->assertSame('postmark-1', $postmark->normalizePayload([
            'MessageID' => 'postmark-1',
            'OriginalRecipient' => 'demo@fallback.test',
            'From' => 'sender@example.net',
            'Subject' => 'Postmark hello',
        ])['provider_id']);

        $timestamp = now()->toIso8601String();
        $ses = app(SesInboundProvider::class);
        $this->assertTrue($ses->verifySignature([], [
            'MessageId' => 'ses-1',
            'Timestamp' => $timestamp,
            'Signature' => hash_hmac('sha256', 'ses-1|'.$timestamp, 'ses-secret'),
        ]));
        $this->assertSame('ses-1', $ses->normalizePayload([
            'mail' => [
                'messageId' => 'ses-1',
                'source' => 'sender@example.net',
                'destination' => ['demo@fallback.test'],
            ],
        ])['provider_id']);
    }

    public function test_provider_intake_rejects_invalid_signatures_and_records_metrics(): void
    {
        Queue::fake();

        $intake = app(InboundMailIntakeService::class)->create([
            'timestamp' => (string) time(),
            'token' => 'token-123',
            'signature' => 'bad-signature',
            'recipient' => 'demo@fallback.test',
        ], provider: 'mailgun');

        $this->assertSame(InboundIntakeStatus::Rejected, $intake->status);
        $this->assertFalse($intake->signature_valid);
        $this->assertDatabaseHas('operations_events', [
            'category' => 'mail',
            'event_type' => 'provider_intake_rejected',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_provider_replay_attempts_and_duplicate_intakes_are_idempotent(): void
    {
        Queue::fake();
        $timestamp = (string) time();
        $payload = [
            'timestamp' => $timestamp,
            'token' => 'token-123',
            'signature' => hash_hmac('sha256', $timestamp.'token-123', 'mailgun-secret'),
            'recipient' => 'demo@fallback.test',
            'sender' => 'sender@example.net',
            'subject' => 'Replay',
        ];

        $first = app(InboundMailIntakeService::class)->create($payload, provider: 'mailgun');
        $second = app(InboundMailIntakeService::class)->create($payload, provider: 'mailgun');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, InboundMailIntake::query()->count());
        Queue::assertPushed(ProcessInboundMailIntake::class, 1);
    }

    public function test_intake_job_is_idempotent_and_records_provider_failures(): void
    {
        $intake = InboundMailIntake::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'mailgun',
            'signature_valid' => true,
            'signature_checked_at' => now(),
            'status' => InboundIntakeStatus::Queued,
            'payload_json' => [
                'timestamp' => (string) time(),
                'recipient' => 'demo@fallback.test',
                'recipients' => [
                    [
                        'type' => 'invalid',
                        'email' => 'demo@fallback.test',
                    ],
                ],
            ],
        ]);

        (new ProcessInboundMailIntake($intake->id))->handle(
            app(InboundMailIntakeService::class),
            app(\App\Services\Mail\EmailMessageStorageService::class),
        );

        $this->assertTrue($intake->fresh()->isFailed());
        $this->assertSame(0, EmailMessage::query()->count());
        $this->assertDatabaseHas('operations_events', [
            'category' => 'mail',
            'event_type' => 'provider_intake_failed',
        ]);

        (new ProcessInboundMailIntake($intake->id))->handle(
            app(InboundMailIntakeService::class),
            app(\App\Services\Mail\EmailMessageStorageService::class),
        );

        $this->assertSame(0, EmailMessage::query()->count());
    }

    public function test_domain_pool_assignment_strategies_and_empty_inventory_fallback_are_stable(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertSame('fallback.test', app(DomainPoolService::class)->selectDomain());

        $this->domain('first.test', 10, 80);
        $this->domain('second.test', 1, 90);

        config(['domains-pool.default_strategy' => DomainAssignmentStrategy::Priority->value]);
        $this->assertSame('second.test', app(DomainPoolService::class)->selectDomain());

        config(['domains-pool.default_strategy' => DomainAssignmentStrategy::Weighted->value]);
        $this->assertSame('second.test', app(DomainPoolService::class)->selectDomain());

        Domain::query()->update(['status' => DomainStatus::Inactive]);
        $this->assertSame('fallback.test', app(DomainPoolService::class)->selectDomain());
    }

    public function test_no_provider_sdk_dependency_or_public_route_is_required(): void
    {
        $this->assertFalse(class_exists('Mailgun\\Mailgun'));
        $this->assertFalse(class_exists('Aws\\Ses\\SesClient'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('inbound.webhook'));
        $this->assertTrue(Schema::hasTable('inbound_mail_intakes'));
    }

    private function domain(string $name, int $priority, int $health): Domain
    {
        return Domain::query()->create([
            'uuid' => (string) Str::uuid(),
            'domain' => $name,
            'status' => DomainStatus::Active,
            'type' => DomainType::Public,
            'tier' => DomainTier::Free,
            'priority' => $priority,
            'health_score' => $health,
            'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
            'last_checked_at' => now(),
        ]);
    }
}
