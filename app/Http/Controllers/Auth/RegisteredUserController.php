<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountTier;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'display_name' => $request->string('name')->toString(),
            'username' => $request->filled('username') ? $request->string('username')->toString() : null,
            'public_slug' => $request->filled('username') ? $request->string('username')->toString() : null,
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        $user->forceFill([
            'status' => UserStatus::Active,
            'account_tier' => AccountTier::Free,
            'api_access_enabled' => false,
            'two_factor_enabled' => false,
            'password_changed_at' => now(),
        ])->save();

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
