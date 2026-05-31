<?php

namespace Tests\Feature;

use App\Enums\InboundIntakeStatus;
use App\Enums\InboundProvider;
use App\Jobs\ProcessInboundMailIntake;
use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Services\Mail\InboundMailIntakeService;
use App\Services\Mail\Providers\LocalInboundProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InboundMailProcessingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_mail_intakes_migration_works(): void
    {
        $this->assertTrue(Schema::hasTable('inbound_mail_intakes'));
        $this->assertTrue(Schema::hasColumns('inbound_mail_intakes', [
            'uuid',
            'provider',
            'provider_message_id',
            'intake_key',
            'signature_valid',
            'signature_checked_at',
            'status',
            'source_ip_hash',
            'headers_json',
            'payload_json',
            'normalized_payload_json',
            'error_message',
            'queued_at',
            'processed_at',
            'failed_at',
        ]));
    }

    public function test_intake_model_casts_and_helpers_work(): void
    {
        $intake = InboundMailIntake::query()->create([
            'uuid' => fake()->uuid(),
            'provider' => InboundProvider::Local,
            'signature_valid' => true,
            'signature_checked_at' => now(),
            'status' => InboundIntakeStatus::Verified,
            'headers_json' => ['x-test' => 'yes'],
            'payload_json' => ['subject' => 'Hello'],
        ]);

        $this->assertSame(InboundProvider::Local, $intake->provider);
        $this->assertSame(InboundIntakeStatus::Verified, $intake->status);
        $this->assertTrue($intake->signature_valid);
        $this->assertSame('yes', $intake->headers_json['x-test']);
        $this->assertTrue($intake->isVerified());

        $intake->markQueued();
        $this->assertTrue($intake->fresh()->isQueued());

        $intake->markProcessing();
        $this->assertSame(InboundIntakeStatus::Processing, $intake->fresh()->status);

        $intake->markProcessed();
        $this->assertTrue($intake->fresh()->isProcessed());

        $intake->markFailed('Safe failure message');
        $this->assertTrue($intake->fresh()->isFailed());
    }

    public function test_local_provider_verifies_safely(): void
    {
        config(['inbound.providers.local.token' => null]);

        $this->assertTrue(app(LocalInboundProvider::class)->verifySignature([], ['subject' => 'Local']));

        config(['inbound.providers.local.token' => 'secret-local-token']);

        $this->assertFalse(app(LocalInboundProvider::class)->verifySignature([], []));
        $this->assertTrue(app(LocalInboundProvider::class)->verifySignature([
            'x-local-inbound-token' => 'secret-local-token',
        ], []));
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['inbound.providers.local.token' => 'secret-local-token']);

        $intake = app(InboundMailIntakeService::class)->create([
            'provider_message_id' => 'invalid-1',
            'mailbox_address' => 'demo@example.com',
        ]);

        $this->assertSame(InboundIntakeStatus::Rejected, $intake->status);
        $this->assertFalse($intake->signature_valid);
        $this->assertSame('Inbound signature verification failed.', $intake->error_message);
        $this->assertSame(0, EmailMessage::query()->count());
    }

    public function test_valid_local_intake_is_queued(): void
    {
        Queue::fake();

        $intake = app(InboundMailIntakeService::class)->create([
            'provider_message_id' => 'queued-1',
            'mailbox_address' => 'demo@example.com',
            'from_email' => 'sender@example.net',
            'subject' => 'Queued',
        ], sourceIp: '127.0.0.1');

        $this->assertSame(InboundIntakeStatus::Queued, $intake->status);
        $this->assertTrue($intake->signature_valid);
        $this->assertNotNull($intake->source_ip_hash);
        $this->assertNotSame('127.0.0.1', $intake->source_ip_hash);

        Queue::assertPushed(ProcessInboundMailIntake::class);
    }

    public function test_process_job_stores_message_using_step09_storage_service(): void
    {
        $intake = InboundMailIntake::query()->create([
            'uuid' => fake()->uuid(),
            'provider' => InboundProvider::Local,
            'provider_message_id' => 'process-1',
            'signature_valid' => true,
            'signature_checked_at' => now(),
            'status' => InboundIntakeStatus::Queued,
            'payload_json' => [
                'mailbox_address' => 'demo@example.com',
                'from_email' => 'sender@example.net',
                'subject' => 'Stored',
                'text_body' => 'Hello from local provider',
                'attachments' => [
                    [
                        'original_filename' => 'note.txt',
                        'mime_type' => 'text/plain',
                        'size' => 10,
                    ],
                ],
            ],
        ]);

        (new ProcessInboundMailIntake($intake->id))->handle(
            app(InboundMailIntakeService::class),
            app(\App\Services\Mail\EmailMessageStorageService::class),
        );

        $intake->refresh();

        $this->assertTrue($intake->isProcessed());
        $this->assertSame('demo@example.com', $intake->normalized_payload_json['mailbox_address']);
        $this->assertSame(1, EmailMessage::query()->count());
        $this->assertSame('Stored', EmailMessage::query()->first()->subject);
        $this->assertSame(1, EmailMessage::query()->first()->attachments()->count());
    }

    public function test_duplicate_intake_does_not_create_duplicate_message(): void
    {
        $service = app(InboundMailIntakeService::class);

        $first = $service->create([
            'provider_message_id' => 'duplicate-provider-id',
            'intake_key' => 'duplicate-key',
            'mailbox_address' => 'demo@example.com',
            'subject' => 'First',
        ]);

        $second = $service->create([
            'provider_message_id' => 'duplicate-provider-id',
            'intake_key' => 'duplicate-key',
            'mailbox_address' => 'demo@example.com',
            'subject' => 'Second',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, InboundMailIntake::query()->count());
        $this->assertSame(1, EmailMessage::query()->count());
        $this->assertSame('First', EmailMessage::query()->first()->subject);
    }

    public function test_failed_job_marks_intake_failed_safely(): void
    {
        $intake = InboundMailIntake::query()->create([
            'uuid' => fake()->uuid(),
            'provider' => InboundProvider::Local,
            'provider_message_id' => 'failed-1',
            'signature_valid' => true,
            'signature_checked_at' => now(),
            'status' => InboundIntakeStatus::Queued,
            'payload_json' => [
                'mailbox_address' => 'demo@example.com',
                'recipients' => [
                    [
                        'type' => 'invalid',
                        'email' => 'demo@example.com',
                    ],
                ],
            ],
        ]);

        (new ProcessInboundMailIntake($intake->id))->handle(
            app(InboundMailIntakeService::class),
            app(\App\Services\Mail\EmailMessageStorageService::class),
        );

        $intake->refresh();

        $this->assertTrue($intake->isFailed());
        $this->assertNotNull($intake->failed_at);
        $this->assertStringContainsString('recipient', strtolower($intake->error_message));
        $this->assertStringNotContainsString("\n", $intake->error_message);
    }

    public function test_no_public_webhook_route_is_exposed_by_default(): void
    {
        $this->assertFalse(Route::has('inbound.webhook'));
        $this->assertFalse(Route::has('mail.inbound'));
    }

    public function test_existing_public_auth_and_admin_routes_still_work(): void
    {
        $this->get('/')->assertOk();
        $this->getJson('/health')->assertOk();
        $this->get('/status')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/admin')->assertForbidden();
    }
}
