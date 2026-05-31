<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('mailbox_address')->nullable()->index();
            $table->string('recipient_local_part')->nullable();
            $table->string('recipient_domain')->nullable()->index();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->string('message_id_header')->nullable();
            $table->text('in_reply_to')->nullable();
            $table->text('references_header')->nullable();
            $table->longText('text_body')->nullable();
            $table->longText('html_body')->nullable();
            $table->longText('sanitized_html_body')->nullable();
            $table->string('status', 32)->index();
            $table->string('parse_status', 32)->index();
            $table->string('intake_source')->nullable();
            $table->string('provider_id')->nullable()->index();
            $table->string('intake_key')->nullable()->index();
            $table->boolean('is_quarantined')->default(false);
            $table->string('quarantine_reason')->nullable();
            $table->unsignedSmallInteger('abuse_score')->default(0);
            $table->string('retention_tier', 32);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
