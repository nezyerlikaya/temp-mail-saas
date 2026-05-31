<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_name')->index();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('payload_hash', 128)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
