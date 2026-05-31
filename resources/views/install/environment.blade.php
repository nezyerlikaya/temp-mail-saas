@extends('install.partials.shell')

@section('installer')
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-white">Environment</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                Environment readiness is shown as safe status only. Secret values are never rendered.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-white/10 bg-slate-900/70 p-4">
                <div class="text-sm text-slate-400">.env file</div>
                <div class="mt-2 font-semibold {{ $status['environment']['env_exists'] ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $status['environment']['env_exists'] ? 'Available' : 'Will be created when needed' }}
                </div>
            </div>
            <div class="rounded-lg border border-white/10 bg-slate-900/70 p-4">
                <div class="text-sm text-slate-400">APP_KEY</div>
                <div class="mt-2 font-semibold {{ $status['environment']['app_key_configured'] ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $status['environment']['app_key_configured'] ? 'Configured' : 'Generated at finish' }}
                </div>
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('installer.requirements') }}" class="rounded-lg border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 hover:border-cyan-300/50">Back</a>
            <a href="{{ route('installer.database') }}" class="rounded-lg bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-200">Next</a>
        </div>
    </div>
@endsection
