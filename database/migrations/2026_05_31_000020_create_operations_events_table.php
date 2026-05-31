<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('category', 32)->index();
            $table->string('event_type', 64)->index();
            $table->string('severity', 32)->index();
            $table->string('status', 32)->index();
            $table->string('source')->nullable();
            $table->string('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_events');
    }
};
