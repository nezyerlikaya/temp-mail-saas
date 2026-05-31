@extends('layouts.app', ['title' => 'Reset password - '.config('tempmail.public_name')])

@section('content')
    <section class="min-h-screen px-6 py-16">
        <div class="mx-auto max-w-md rounded-3xl border border-white/10 bg-white/[0.04] p-8">
            <h1 class="text-3xl font-bold text-white">Reset password</h1>
            <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <div>
                    <label for="email" class="text-sm font-medium text-slate-200">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email', $request->email) }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                </div>
                <div>
                    <label for="password" class="text-sm font-medium text-slate-200">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-medium text-slate-200">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                </div>
                @if ($errors->any()) <p class="text-sm text-red-300">{{ $errors->first() }}</p> @endif
                <button class="w-full rounded-xl bg-cyan-300 px-4 py-3 font-semibold text-slate-950 hover:bg-cyan-200 focus:ring-2 focus:ring-cyan-300">Reset password</button>
            </form>
        </div>
    </section>
@endsection
