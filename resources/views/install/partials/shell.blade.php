@extends('layouts.app', ['title' => 'Installer - '.config('tempmail.name')])

@section('content')
    <section class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300">Temp Mail SaaS</p>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Installation</h1>
                </div>
                <div class="rounded-lg border border-emerald-300/30 bg-emerald-300/10 px-4 py-2 text-sm text-emerald-100">
                    Recovery-ready setup
                </div>
            </div>

            @include('install.partials.progress')

            <div class="mt-6 rounded-lg border border-white/10 bg-white/[0.04] p-6 shadow-2xl shadow-cyan-950/30 sm:p-8">
                @yield('installer')
            </div>
        </div>
    </section>
@endsection
