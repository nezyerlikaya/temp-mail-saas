<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->index();
            $table->string('category', 32)->index();
            $table->string('priority', 32)->index();
            $table->string('status', 32)->index();
            $table->string('title');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index(['type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};
