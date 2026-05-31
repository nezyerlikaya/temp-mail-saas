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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk');
            $table->string('directory');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('extension', 32)->nullable();
            $table->string('mime_type')->index();
            $table->unsignedBigInteger('size');
            $table->string('checksum', 128)->nullable()->index();
            $table->string('visibility', 32);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('status', 32)->index();
            $table->string('storage_driver', 64);
            $table->string('storage_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
