<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('queue_name')->index();
            $table->unsignedInteger('pending_jobs')->default(0);
            $table->unsignedInteger('failed_jobs')->default(0);
            $table->unsignedInteger('processed_jobs')->default(0);
            $table->timestamp('measured_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_metrics');
    }
};
