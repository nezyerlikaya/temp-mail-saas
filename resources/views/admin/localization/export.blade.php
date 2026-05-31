@extends('layouts.admin', [
    'title' => 'Export Translations',
    'heading' => 'Export Translations',
    'description' => 'Download grouped JSON for a selected language.',
])

@section('admin')
    <x-admin.card title="JSON Export">
        <form method="GET" action="{{ route('admin.localization.export') }}" class="grid gap-4 md:grid-cols-3">
            <label class="md:col-span-2 text-sm text-slate-300">
                Language
                <select name="language_id" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
                    @foreach ($languages as $language)
                        <option value="{{ $language->id }}">{{ $language->name }} ({{ $language->code }})</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end">
                <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-2 focus:ring-cyan-100">Export JSON</button>
            </div>
        </form>
    </x-admin.card>
@endsection
