@extends('layouts.app', ['title' => 'Forgot password - '.config('tempmail.public_name')])

@section('content')
    <section class="min-h-screen px-6 py-16">
        <div class="mx-auto max-w-md rounded-3xl border border-white/10 bg-white/[0.04] p-8">
            <h1 class="text-3xl font-bold text-white">Forgot password</h1>
            <p class="mt-2 text-sm text-slate-300">Enter your email address. We will send reset instructions when the account is eligible.</p>
            <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
                @csrf
                @include('auth.partials.status')
                <div>
                    <label for="email" class="text-sm font-medium text-slate-200">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-error" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                    @error('email') <p id="email-error" class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-xl bg-cyan-300 px-4 py-3 font-semibold text-slate-950 hover:bg-cyan-200 focus:ring-2 focus:ring-cyan-300">Send reset link</button>
            </form>
            <a href="{{ route('login') }}" class="mt-6 inline-block text-sm text-cyan-300 hover:text-cyan-200">Back to login</a>
        </div>
    </section>
@endsection
