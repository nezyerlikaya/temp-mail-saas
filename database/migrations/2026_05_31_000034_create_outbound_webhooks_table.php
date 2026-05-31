<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_webhooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('url', 2048);
            $table->string('status', 32)->index();
            $table->string('secret_hash')->nullable();
            $table->json('subscribed_events')->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhooks');
    }
};
