@extends('install.partials.shell')

@section('installer')
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-white">Requirements</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                Server requirements are checked without exposing credentials or internal secrets.
            </p>
        </div>

        <div class="space-y-2">
            @foreach ($requirements['checks'] as $check)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-white/10 bg-slate-900/70 p-4">
                    <div>
                        <div class="font-medium text-white">{{ $check['label'] }}</div>
                        @isset($check['current'])
                            <div class="mt-1 text-xs text-slate-400">Current: {{ $check['current'] }}</div>
                        @endisset
                    </div>
                    <span class="rounded-md px-3 py-1 text-xs font-semibold {{ $check['ok'] ? 'bg-emerald-300/15 text-emerald-200' : 'bg-amber-300/15 text-amber-200' }}">
                        {{ $check['ok'] ? 'OK' : 'Needs attention' }}
                    </span>
                </div>
            @endforeach
        </div>

        <div class="flex justify-between">
            <a href="{{ route('installer.index') }}" class="rounded-lg border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 hover:border-cyan-300/50">Back</a>
            <a href="{{ route('installer.environment') }}" class="rounded-lg bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-200">Next</a>
        </div>
    </div>
@endsection
