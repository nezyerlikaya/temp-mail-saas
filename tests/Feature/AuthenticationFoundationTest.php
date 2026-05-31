<?php

namespace Tests\Feature;

use App\Enums\AccountTier;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_registration_pages_load(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
        $this->get('/register')->assertOk()->assertSee('Create account');
    }

    public function test_user_can_register_with_safe_defaults(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Example User',
            'username' => ' Example User ',
            'email' => 'example@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'website' => '',
            'form_started_at' => now()->subSeconds(3)->timestamp,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'example@example.com')->firstOrFail();

        $this->assertSame('example-user', $user->username);
        $this->assertSame('example-user', $user->public_slug);
        $this->assertSame('Example User', $user->display_name);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(AccountTier::Free, $user->account_tier);
        $this->assertFalse($user->api_access_enabled);
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_reserved_username_cannot_register(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Example User',
            'username' => 'ADMIN',
            'email' => 'example@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'website' => '',
            'form_started_at' => now()->subSeconds(3)->timestamp,
        ])->assertRedirect('/register')->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Suspended,
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'The provided credentials are incorrect.',
            ]);

        $this->assertGuest();
    }

    public function test_dashboard_requires_auth_and_loads_for_a_user(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('User dashboard')
            ->assertSee('Free')
            ->assertSee('Active');
    }

    public function test_password_reset_foundation_loads_and_uses_enumeration_safe_message(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $this->get('/forgot-password')->assertOk()->assertSee('Forgot password');
        $this->get('/reset-password/sample-token?email=known@example.com')
            ->assertOk()
            ->assertSee('Reset password');

        $known = $this->post('/forgot-password', ['email' => 'known@example.com']);
        $unknown = $this->post('/forgot-password', ['email' => 'missing@example.com']);

        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    public function test_email_verification_notice_route_exists_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk()
            ->assertSee('Verify your email');
    }

    public function test_existing_public_routes_still_work(): void
    {
        $this->get('/')->assertOk();
        $this->getJson('/health')->assertOk();
        $this->get('/status')->assertOk();
        $this->get('/up')->assertOk();
    }
}
