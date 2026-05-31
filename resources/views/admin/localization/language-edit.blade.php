@extends('layouts.admin', [
    'title' => 'Edit Language',
    'heading' => 'Edit Language',
    'description' => 'Update locale metadata while preserving default and active-language safeguards.',
])

@section('admin')
    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-red-400/20 bg-red-400/10 p-4 text-sm text-red-100">{{ $errors->first() }}</div>
    @endif

    <x-admin.card title="Language Details">
        <form method="POST" action="{{ route('admin.localization.languages.update', $language) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            <label class="text-sm text-slate-300">
                Locale code
                <input name="code" value="{{ old('code', $language->code) }}" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
            </label>
            <label class="text-sm text-slate-300">
                Direction
                <select name="direction" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
                    <option value="ltr" @selected($language->direction->value === 'ltr')>LTR</option>
                    <option value="rtl" @selected($language->direction->value === 'rtl')>RTL</option>
                </select>
            </label>
            <label class="text-sm text-slate-300">
                Display name
                <input name="name" value="{{ old('name', $language->name) }}" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
            </label>
            <label class="text-sm text-slate-300">
                Native name
                <input name="native_name" value="{{ old('native_name', $language->native_name) }}" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
            </label>
            <label class="text-sm text-slate-300">
                Sort order
                <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $language->sort_order) }}" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
            </label>
            <div class="flex items-center gap-4 pt-6">
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $language->is_active)) class="rounded border-white/10 bg-slate-950 text-cyan-300">
                    Active
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $language->is_default)) class="rounded border-white/10 bg-slate-950 text-cyan-300">
                    Default
                </label>
            </div>
            <div class="md:col-span-2 flex gap-3">
                <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-2 focus:ring-cyan-100">Save language</button>
                <a href="{{ route('admin.localization.languages') }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-200 hover:border-cyan-300/60">Cancel</a>
            </div>
        </form>
    </x-admin.card>
@endsection
