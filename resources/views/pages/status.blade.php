@extends('layouts.app', ['title' => 'Service Status - '.config('tempmail.public_name')])

@section('content')
    <section class="min-h-screen px-6 py-16 sm:px-10 lg:px-16">
        <div class="mx-auto max-w-4xl">
            <a href="{{ route('home') }}" class="text-sm font-medium text-cyan-300 hover:text-cyan-200">
                Back to home
            </a>

            <div class="mt-8 rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-2xl shadow-cyan-950/40 sm:p-12">
                <p class="text-sm font-medium uppercase tracking-[0.3em] text-cyan-300">
                    Public Status
                </p>

                <h1 class="mt-4 text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    {{ $status['application'] }}
                </h1>

                <div class="mt-8 rounded-2xl border border-white/10 bg-slate-900/70 p-6">
                    <div class="text-sm text-slate-400">Service status</div>
                    <div class="mt-2 text-2xl font-semibold {{ $status['status'] === 'ok' ? 'text-emerald-300' : 'text-amber-300' }}">
                        {{ $status['availability'] }}
                    </div>
                </div>

                <p class="mt-6 text-slate-300">
                    This page shows only public-safe availability information. Internal environment,
                    credential, and infrastructure details are intentionally hidden.
                </p>
            </div>
        </div>
    </section>
@endsection
