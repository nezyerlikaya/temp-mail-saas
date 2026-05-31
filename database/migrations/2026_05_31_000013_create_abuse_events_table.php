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
        Schema::create('abuse_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_type', 64)->index();
            $table->string('severity', 32)->index();
            $table->string('status', 32)->index();
            $table->string('ip_hash', 128)->nullable()->index();
            $table->string('session_hash', 128)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route_name')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('method', 16)->nullable();
            $table->string('user_agent_hash', 128)->nullable();
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abuse_events');
    }
};
