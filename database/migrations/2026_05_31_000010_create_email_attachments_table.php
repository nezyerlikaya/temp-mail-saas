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
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('email_message_id')->constrained('email_messages')->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('original_filename')->nullable();
            $table->string('safe_filename')->nullable();
            $table->string('mime_type')->nullable()->index();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 128)->nullable()->index();
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('scan_status', 32);
            $table->string('status', 32)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
