<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_customer_id');
            $table->string('email')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_customers');
    }
};
