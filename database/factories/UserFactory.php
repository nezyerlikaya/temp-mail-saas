<?php

namespace Database\Factories;

use App\Enums\AccountTier;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'username' => null,
            'display_name' => null,
            'public_slug' => null,
            'status' => UserStatus::Active,
            'avatar_disk' => null,
            'avatar_path' => null,
            'avatar_mime' => null,
            'avatar_size' => null,
            'avatar_hash' => null,
            'avatar_updated_at' => null,
            'locale' => null,
            'timezone' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'password_changed_at' => null,
            'account_tier' => AccountTier::Free,
            'api_access_enabled' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
