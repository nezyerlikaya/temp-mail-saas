<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_health_checks', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->index();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('message')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_health_checks');
    }
};
