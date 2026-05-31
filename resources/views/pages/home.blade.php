@extends('layouts.app', ['title' => config('tempmail.name')])

@section('content')
    <section class="min-h-screen px-6 py-16 sm:px-10 lg:px-16">
        <div class="mx-auto flex max-w-5xl flex-col gap-12">
            <nav class="flex items-center justify-between">
                <div class="text-sm font-semibold uppercase tracking-[0.35em] text-cyan-300">
                    Temp Mail SaaS
                </div>
                <div class="rounded-full border border-cyan-400/30 px-4 py-2 text-sm text-cyan-100">
                    STEP01 Ready
                </div>
            </nav>

            <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-2xl shadow-cyan-950/40 sm:p-12">
                <div class="max-w-3xl">
                    <p class="mb-4 text-sm font-medium uppercase tracking-[0.3em] text-cyan-300">
                        Modular Monolith Foundation
                    </p>

                    <h1 class="text-4xl font-bold tracking-tight text-white sm:text-6xl">
                        {{ config('tempmail.name') }}
                    </h1>

                    <p class="mt-6 text-lg leading-8 text-slate-300">
                        Architecture is ready for future mail, domain, billing, abuse, SEO, admin,
                        and API modules. Controllers stay thin, services own business workflows,
                        and contracts keep future integrations replaceable.
                    </p>
                </div>

                <div class="mt-10 grid gap-4 sm:grid-cols-3" x-data="{ ready: true }">
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-5">
                        <div class="text-sm text-slate-400">Pattern</div>
                        <div class="mt-2 font-semibold text-white">Services + Contracts</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-5">
                        <div class="text-sm text-slate-400">Frontend</div>
                        <div class="mt-2 font-semibold text-white">Blade + Alpine + Tailwind</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-5">
                        <div class="text-sm text-slate-400">Status</div>
                        <div class="mt-2 font-semibold text-emerald-300" x-text="ready ? 'Foundation ready' : 'Preparing'"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
