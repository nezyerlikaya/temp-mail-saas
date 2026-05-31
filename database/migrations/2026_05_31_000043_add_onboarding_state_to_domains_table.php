<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('onboarding_state', 32)->default('draft')->index()->after('status');
        });

        DB::table('domains')
            ->where('status', 'active')
            ->update(['onboarding_state' => 'active']);
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('onboarding_state');
        });
    }
};
