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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->unique()->after('name');
            $table->string('display_name')->nullable()->after('username');
            $table->string('public_slug')->nullable()->unique()->after('display_name');

            $table->string('status', 32)->default('active')->index()->after('public_slug');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->timestamp('last_seen_at')->nullable()->index()->after('last_login_at');

            $table->string('avatar_disk')->nullable()->after('last_seen_at');
            $table->string('avatar_path')->nullable()->after('avatar_disk');
            $table->string('avatar_mime', 100)->nullable()->after('avatar_path');
            $table->unsignedBigInteger('avatar_size')->nullable()->after('avatar_mime');
            $table->string('avatar_hash', 128)->nullable()->after('avatar_size');
            $table->timestamp('avatar_updated_at')->nullable()->after('avatar_hash');

            $table->string('locale', 16)->nullable()->after('avatar_updated_at');
            $table->string('timezone', 64)->nullable()->after('locale');

            $table->boolean('two_factor_enabled')->default(false)->after('timezone');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
            $table->timestamp('password_changed_at')->nullable()->after('two_factor_confirmed_at');

            $table->string('account_tier', 32)->default('free')->index()->after('password_changed_at');
            $table->boolean('api_access_enabled')->default(false)->after('account_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['public_slug']);
            $table->dropIndex(['status']);
            $table->dropIndex(['last_seen_at']);
            $table->dropIndex(['account_tier']);

            $table->dropColumn([
                'username',
                'display_name',
                'public_slug',
                'status',
                'last_login_at',
                'last_seen_at',
                'avatar_disk',
                'avatar_path',
                'avatar_mime',
                'avatar_size',
                'avatar_hash',
                'avatar_updated_at',
                'locale',
                'timezone',
                'two_factor_enabled',
                'two_factor_confirmed_at',
                'password_changed_at',
                'account_tier',
                'api_access_enabled',
            ]);
        });
    }
};
