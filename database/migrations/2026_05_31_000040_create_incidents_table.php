<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('category', 64)->index();
            $table->string('severity', 32)->index();
            $table->string('status', 32)->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamp('detected_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
