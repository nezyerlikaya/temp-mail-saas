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
        Schema::create('inbound_mail_intakes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 32)->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('intake_key')->nullable()->index();
            $table->boolean('signature_valid')->default(false)->index();
            $table->timestamp('signature_checked_at')->nullable();
            $table->string('status', 32)->index();
            $table->string('source_ip_hash', 128)->nullable();
            $table->json('headers_json')->nullable();
            $table->longText('payload_json')->nullable();
            $table->longText('normalized_payload_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_mail_intakes');
    }
};
