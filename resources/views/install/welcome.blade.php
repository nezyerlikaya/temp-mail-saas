@extends('install.partials.shell')

@section('installer')
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-white">Welcome</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                This installer checks the environment, database readiness, and recovery state before locking the setup flow.
            </p>
        </div>

        <dl class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-white/10 bg-slate-900/70 p-4">
                <dt class="text-xs uppercase tracking-[0.2em] text-slate-400">Environment</dt>
                <dd class="mt-2 font-semibold {{ $status['environment']['env_exists'] ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $status['environment']['env_exists'] ? 'Detected' : 'Missing' }}
                </dd>
            </div>
            <div class="rounded-lg border border-white/10 bg-slate-900/70 p-4">
                <dt class="text-xs uppercase tracking-[0.2em] text-slate-400">Application key</dt>
                <dd class="mt-2 font-semibold {{ $status['environment']['app_key_configured'] ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $status['environment']['app_key_configured'] ? 'Configured' : 'Missing' }}
                </dd>
            </div>
            <div class="rounded-lg border border-white/10 bg-slate-900/70 p-4">
                <dt class="text-xs uppercase tracking-[0.2em] text-slate-400">Installer lock</dt>
                <dd class="mt-2 font-semibold {{ $status['lock']['locked'] ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $status['lock']['locked'] ? 'Locked' : 'Open' }}
                </dd>
            </div>
        </dl>

        <div class="flex justify-end">
            <a href="{{ route('installer.requirements') }}" class="rounded-lg bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-200">Next</a>
        </div>
    </div>
@endsection
