@extends('layouts.app', ['title' => 'Dashboard - '.config('tempmail.public_name')])

@section('content')
    <section class="min-h-screen px-6 py-16 sm:px-10 lg:px-16">
        <div class="mx-auto max-w-5xl">
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="text-sm font-medium text-cyan-300 hover:text-cyan-200">Home</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-200 hover:border-cyan-300/50 hover:text-white focus:ring-2 focus:ring-cyan-300">Log out</button>
                </form>
            </div>
            <div class="mt-8 rounded-3xl border border-white/10 bg-white/[0.04] p-8 sm:p-12">
                <div class="flex items-center gap-5">
                    <img src="{{ $avatarUrl }}" alt="" class="h-16 w-16 rounded-full">
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-cyan-300">User dashboard</p>
                        <h1 class="mt-2 text-3xl font-bold text-white">Hello, {{ $user->display_name ?: $user->name }}</h1>
                    </div>
                </div>
                @if (! $user->hasVerifiedEmail())
                    <div class="mt-8 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
                        Your email address is not verified yet. <a href="{{ route('verification.notice') }}" class="font-semibold underline">View verification options</a>
                    </div>
                @endif
                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-5"><div class="text-sm text-slate-400">Account tier</div><div class="mt-2 font-semibold text-white">{{ ucfirst($user->account_tier->value) }}</div></div>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-5"><div class="text-sm text-slate-400">Account status</div><div class="mt-2 font-semibold text-white">{{ ucfirst($user->status->value) }}</div></div>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-5"><div class="text-sm text-slate-400">Email verification</div><div class="mt-2 font-semibold text-white">{{ $user->hasVerifiedEmail() ? 'Verified' : 'Pending' }}</div></div>
                </div>
                <p class="mt-8 text-sm text-slate-400">Account access is ready. Mailbox functionality is intentionally not included in this step.</p>
            </div>
        </div>
    </section>
@endsection
