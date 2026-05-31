<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source', 64)->index();
            $table->string('alert_type', 64)->index();
            $table->string('severity', 32)->index();
            $table->string('status', 32)->index();
            $table->string('message')->nullable();
            $table->timestamp('triggered_at')->index();
            $table->timestamp('acknowledged_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_alerts');
    }
};
