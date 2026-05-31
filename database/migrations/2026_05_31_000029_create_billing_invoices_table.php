<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('billing_customer_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_invoice_id');
            $table->string('status', 32)->index();
            $table->string('currency', 3)->nullable();
            $table->integer('amount_due')->nullable();
            $table->integer('amount_paid')->nullable();
            $table->string('hosted_invoice_url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
