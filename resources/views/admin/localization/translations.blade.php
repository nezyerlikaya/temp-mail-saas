@extends('layouts.admin', [
    'title' => 'Translations',
    'heading' => 'Translations',
    'description' => 'Search, filter, and update translation values with audit-safe writes.',
])

@section('admin')
    @if (session('status'))
        <div class="mb-4 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-4 text-sm text-emerald-100">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.localization.translations') }}" class="mb-4 grid gap-3 rounded-2xl border border-white/10 bg-slate-900/70 p-4 md:grid-cols-4">
        <label class="text-sm text-slate-300">
            Search
            <input name="search" value="{{ $filters['search'] }}" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
        </label>
        <label class="text-sm text-slate-300">
            Language
            <select name="language_id" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
                <option value="">All languages</option>
                @foreach ($languages as $language)
                    <option value="{{ $language->id }}" @selected((string) $filters['languageId'] === (string) $language->id)>{{ $language->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm text-slate-300">
            Namespace
            <select name="group" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
                <option value="">All namespaces</option>
                @foreach ($groups as $group)
                    <option value="{{ $group }}" @selected($filters['group'] === $group)>{{ $group }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-2 focus:ring-cyan-100">Apply filters</button>
            <a href="{{ route('admin.localization.translations') }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-200 hover:border-cyan-300/60">Reset</a>
        </div>
    </form>

    @if ($translations->isEmpty())
        <x-admin.empty-state message="No translations matched the current filters." />
    @else
        <form method="POST" action="{{ route('admin.localization.translations.update') }}">
            @csrf
            @method('PUT')
            <x-admin.table :headers="['Language', 'Key', 'Value']">
                @foreach ($translations as $translation)
                    <tr>
                        <td class="px-4 py-3 text-sm text-slate-300">
                            {{ $translation->language?->name }}
                            <div class="text-xs text-slate-500">{{ $translation->language?->code }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-white">
                            {{ $translation->group }}.{{ $translation->key }}
                            @if ($translation->is_custom)
                                <span class="ml-2 rounded-full bg-cyan-400/10 px-2 py-0.5 text-xs text-cyan-200">Custom</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <label class="sr-only" for="translation-{{ $translation->id }}">Value for {{ $translation->group }}.{{ $translation->key }}</label>
                            <textarea id="translation-{{ $translation->id }}" name="translations[{{ $translation->id }}]" rows="2" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">{{ old('translations.'.$translation->id, $translation->value) }}</textarea>
                        </td>
                    </tr>
                @endforeach
            </x-admin.table>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-2 focus:ring-cyan-100">Save visible translations</button>
                {{ $translations->links() }}
            </div>
        </form>
    @endif
@endsection
