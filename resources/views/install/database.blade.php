@extends('install.partials.shell')

@section('installer')
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-white">Database</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                The installer validates connectivity only. Migrations are not run automatically in this step.
            </p>
        </div>

        <div class="rounded-lg border border-white/10 bg-slate-900/70 p-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm text-slate-400">Connection</div>
                    <div class="mt-1 font-semibold text-white">{{ $database['connection'] }}</div>
                </div>
                <span class="rounded-md px-3 py-1 text-xs font-semibold {{ $database['ok'] ? 'bg-emerald-300/15 text-emerald-200' : 'bg-amber-300/15 text-amber-200' }}">
                    {{ $database['ok'] ? 'Ready' : 'Needs attention' }}
                </span>
            </div>
            <div class="mt-4 text-sm text-slate-300">
                Driver: {{ $database['driver'] ?: 'not configured' }}
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('installer.environment') }}" class="rounded-lg border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 hover:border-cyan-300/50">Back</a>
            <a href="{{ route('installer.finish') }}" class="rounded-lg bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-200">Next</a>
        </div>
    </div>
@endsection
