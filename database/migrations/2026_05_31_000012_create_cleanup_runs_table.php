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
        Schema::create('cleanup_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 32)->index();
            $table->string('status', 32)->index();
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('messages_scanned')->default(0);
            $table->unsignedInteger('messages_expired')->default(0);
            $table->unsignedInteger('messages_deleted')->default(0);
            $table->unsignedInteger('intakes_deleted')->default(0);
            $table->unsignedInteger('attachments_affected')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cleanup_runs');
    }
};
