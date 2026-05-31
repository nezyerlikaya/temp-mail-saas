@extends('layouts.admin', [
    'title' => 'Import Translations',
    'heading' => 'Import Translations',
    'description' => 'Safely import grouped JSON into an existing language.',
])

@section('admin')
    @if (session('status'))
        <div class="mb-4 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-4 text-sm text-emerald-100">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-red-400/20 bg-red-400/10 p-4 text-sm text-red-100">{{ $errors->first() }}</div>
    @endif

    <x-admin.card title="JSON Import">
        <form method="POST" action="{{ route('admin.localization.import.store') }}" class="space-y-4">
            @csrf
            <label class="block text-sm text-slate-300">
                Target language
                <select name="language_id" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
                    @foreach ($languages as $language)
                        <option value="{{ $language->id }}">{{ $language->name }} ({{ $language->code }})</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm text-slate-300">
                JSON payload
                <textarea name="json" rows="12" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 font-mono text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-300" placeholder='{"app":{"welcome":"Welcome"}}'>{{ old('json') }}</textarea>
            </label>
            <p class="text-xs text-slate-400">Expected shape: {"namespace":{"key":"value"}}. Imports update matching keys and create missing keys for the selected language.</p>
            <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-2 focus:ring-cyan-100">Import JSON</button>
        </form>
    </x-admin.card>
@endsection
