<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_activation_audits', function (Blueprint $table): void {
            $table->string('review_type')->nullable()->after('performed_by')->index();
            $table->string('recommendation')->nullable()->after('review_type');
        });
    }

    public function down(): void
    {
        Schema::table('provider_activation_audits', function (Blueprint $table): void {
            $table->dropColumn(['review_type', 'recommendation']);
        });
    }
};
