<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('domain')->unique();
            $table->string('status', 32)->index();
            $table->string('type', 32);
            $table->string('tier', 32)->index();
            $table->unsignedSmallInteger('priority')->default(100)->index();
            $table->unsignedSmallInteger('health_score')->default(100)->index();
            $table->string('assignment_strategy', 32);
            $table->json('metadata')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
