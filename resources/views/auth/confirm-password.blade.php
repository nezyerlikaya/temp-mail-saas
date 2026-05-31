@extends('layouts.app', ['title' => 'Confirm password - '.config('tempmail.public_name')])

@section('content')
    <section class="min-h-screen px-6 py-16">
        <div class="mx-auto max-w-md rounded-3xl border border-white/10 bg-white/[0.04] p-8">
            <h1 class="text-3xl font-bold text-white">Confirm password</h1>
            <p class="mt-2 text-sm text-slate-300">Confirm your password before continuing.</p>
            <form method="POST" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label for="password" class="text-sm font-medium text-slate-200">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-error" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                    @error('password') <p id="password-error" class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-xl bg-cyan-300 px-4 py-3 font-semibold text-slate-950 hover:bg-cyan-200 focus:ring-2 focus:ring-cyan-300">Confirm password</button>
            </form>
        </div>
    </section>
@endsection
