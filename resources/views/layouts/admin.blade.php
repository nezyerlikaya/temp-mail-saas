@extends('layouts.app', ['title' => ($title ?? 'Admin Operations').' - '.config('tempmail.public_name')])

@section('content')
    <div class="min-h-screen px-6 py-8 sm:px-10 lg:px-12">
        <div class="mx-auto max-w-7xl">
            <header class="mb-8 rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-[0.3em] text-cyan-300">Admin</p>
                        <h1 class="mt-2 text-3xl font-bold text-white">{{ $heading ?? 'Operations Center' }}</h1>
                        <p class="mt-2 text-sm text-slate-300">{{ $description ?? 'Read-only operational visibility for staff.' }}</p>
                    </div>
                    <nav aria-label="Admin navigation" class="flex flex-wrap gap-2 text-sm">
                        @foreach ([
                            'admin.operations' => 'Operations',
                            'admin.health' => 'Health',
                            'admin.queue' => 'Queue',
                            'admin.domains' => 'Domains',
                            'admin.abuse' => 'Abuse',
                            'admin.billing' => 'Billing',
                            'admin.audit' => 'Audit',
                        ] as $route => $label)
                            <a href="{{ route($route) }}" class="rounded-xl border border-white/10 px-3 py-2 text-slate-200 hover:border-cyan-300/60 hover:text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            </header>

            @yield('admin')
        </div>
    </div>
@endsection
