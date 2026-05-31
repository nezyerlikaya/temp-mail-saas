<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_activation_audits', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('previous_state', 32)->index();
            $table->string('new_state', 32)->index();
            $table->string('reason')->nullable();
            $table->string('performed_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_activation_audits');
    }
};
