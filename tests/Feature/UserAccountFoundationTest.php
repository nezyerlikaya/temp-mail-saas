<?php

namespace Tests\Feature;

use App\DTOs\User\UserProfileData;
use App\Enums\AccountTier;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\User\AvatarMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserAccountFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_account_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
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
        ]));
    }

    public function test_user_enum_and_boolean_casts_work(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Suspended,
            'account_tier' => AccountTier::Premium,
            'two_factor_enabled' => true,
            'api_access_enabled' => true,
        ]);

        $this->assertSame(UserStatus::Suspended, $user->status);
        $this->assertSame(AccountTier::Premium, $user->account_tier);
        $this->assertTrue($user->two_factor_enabled);
        $this->assertTrue($user->api_access_enabled);
    }

    public function test_public_profile_dto_does_not_expose_email(): void
    {
        $user = User::factory()->create([
            'username' => 'sample-user',
            'display_name' => 'Sample User',
            'public_slug' => 'sample-user',
        ]);

        $profile = UserProfileData::fromUser($user, app(AvatarMetadataService::class))->toArray();

        $this->assertArrayNotHasKey('email', $profile);
        $this->assertSame('sample-user', $profile['username']);
        $this->assertSame('free', $profile['account_tier']);
        $this->assertSame('active', $profile['status']);
    }
}
