@extends('install.partials.shell')

@section('installer')
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-white">Finish</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                Completing setup creates the installer lock and sends staff users to the reserved admin login path.
            </p>
        </div>

        <div class="rounded-lg border border-white/10 bg-slate-900/70 p-5">
            <div class="text-sm text-slate-400">Current mode</div>
            <div class="mt-2 font-semibold {{ $status['recovery'] ? 'text-amber-300' : 'text-emerald-300' }}">
                {{ $status['recovery'] ? 'Recovery available' : 'Ready to lock installer' }}
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('installer.database') }}" class="rounded-lg border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 hover:border-cyan-300/50">Back</a>
            <form method="POST" action="{{ route('installer.complete') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-200">Finish installation</button>
            </form>
        </div>
    </div>
@endsection
