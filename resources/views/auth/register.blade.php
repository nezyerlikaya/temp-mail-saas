@extends('layouts.app', ['title' => 'Register - '.config('tempmail.public_name')])

@section('content')
    <section class="min-h-screen px-6 py-16">
        <div class="mx-auto max-w-md">
            <a href="{{ route('home') }}" class="text-sm font-medium text-cyan-300 hover:text-cyan-200">Back to home</a>
            <div class="mt-8 rounded-3xl border border-white/10 bg-white/[0.04] p-8">
                <h1 class="text-3xl font-bold text-white">Create account</h1>
                <p class="mt-2 text-sm text-slate-300">Set up a normal user account.</p>
                <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
                    @csrf
                    @include('auth.partials.status')
                    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                    <input type="hidden" name="form_started_at" value="{{ now()->timestamp }}">
                    <div>
                        <label for="name" class="text-sm font-medium text-slate-200">Name</label>
                        <input id="name" name="name" type="text" autocomplete="name" required value="{{ old('name') }}" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="name-error" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                        @error('name') <p id="name-error" class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="username" class="text-sm font-medium text-slate-200">Username <span class="text-slate-400">(optional)</span></label>
                        <input id="username" name="username" type="text" autocomplete="username" value="{{ old('username') }}" aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}" aria-describedby="username-error" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                        @error('username') <p id="username-error" class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="text-sm font-medium text-slate-200">Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-error" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                        @error('email') <p id="email-error" class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password" class="text-sm font-medium text-slate-200">Password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-error" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                        @error('password') <p id="password-error" class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="text-sm font-medium text-slate-200">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                    </div>
                    <button class="w-full rounded-xl bg-cyan-300 px-4 py-3 font-semibold text-slate-950 outline-none hover:bg-cyan-200 focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950">Create account</button>
                </form>
                <p class="mt-6 text-sm text-slate-300">Already registered? <a href="{{ route('login') }}" class="text-cyan-300 hover:text-cyan-200">Log in</a></p>
            </div>
        </div>
    </section>
@endsection
