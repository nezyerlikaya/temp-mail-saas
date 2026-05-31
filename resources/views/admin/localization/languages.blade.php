@extends('layouts.admin', [
    'title' => 'Languages',
    'heading' => 'Languages',
    'description' => 'Create and maintain available locales without changing public route structures.',
])

@section('admin')
    @if (session('status'))
        <div class="mb-4 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-4 text-sm text-emerald-100">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-red-400/20 bg-red-400/10 p-4 text-sm text-red-100">{{ $errors->first() }}</div>
    @endif

    <x-admin.card title="Create Language">
        <form method="POST" action="{{ route('admin.localization.languages.store') }}" class="grid gap-4 md:grid-cols-6">
            @csrf
            <label class="md:col-span-1 text-sm text-slate-300">
                Locale code
                <input name="code" value="{{ old('code') }}" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
            </label>
            <label class="md:col-span-2 text-sm text-slate-300">
                Display name
                <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
            </label>
            <label class="md:col-span-2 text-sm text-slate-300">
                Native name
                <input name="native_name" value="{{ old('native_name') }}" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
            </label>
            <label class="text-sm text-slate-300">
                Direction
                <select name="direction" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-300">
                    <option value="ltr">LTR</option>
                    <option value="rtl">RTL</option>
                </select>
            </label>
            <div class="md:col-span-6 flex flex-wrap items-center gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-white/10 bg-slate-950 text-cyan-300">
                    Active
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_default" value="1" class="rounded border-white/10 bg-slate-950 text-cyan-300">
                    Default
                </label>
                <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-2 focus:ring-cyan-100">Create language</button>
            </div>
        </form>
    </x-admin.card>

    <div class="mt-6">
        @if ($languages->isEmpty())
            <x-admin.empty-state message="No languages have been created yet." />
        @else
            <x-admin.table :headers="['Language', 'Direction', 'Status', 'Actions']">
                @foreach ($languages as $language)
                    <tr>
                        <td class="px-4 py-3 text-sm text-white">
                            <div class="font-medium">{{ $language->name }}</div>
                            <div class="text-xs text-slate-400">{{ $language->code }} / {{ $language->native_name }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-300">{{ strtoupper($language->direction->value) }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $language->is_active ? 'bg-emerald-400/10 text-emerald-200' : 'bg-slate-400/10 text-slate-300' }}">{{ $language->is_active ? 'Active' : 'Inactive' }}</span>
                            @if ($language->is_default)
                                <span class="ml-2 rounded-full bg-cyan-400/10 px-2.5 py-1 text-xs font-medium text-cyan-200">Default</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.localization.languages.edit', $language) }}" class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-200 hover:border-cyan-300/60">Edit</a>
                                @if (! $language->is_active)
                                    <form method="POST" action="{{ route('admin.localization.languages.activate', $language) }}">@csrf @method('PATCH')<button class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-200 hover:border-emerald-300/60">Activate</button></form>
                                @else
                                    <form method="POST" action="{{ route('admin.localization.languages.deactivate', $language) }}">@csrf @method('PATCH')<button class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-200 hover:border-amber-300/60">Deactivate</button></form>
                                @endif
                                @if (! $language->is_default)
                                    <form method="POST" action="{{ route('admin.localization.languages.default', $language) }}">@csrf @method('PATCH')<button class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-200 hover:border-cyan-300/60">Set default</button></form>
                                    <form method="POST" action="{{ route('admin.localization.languages.destroy', $language) }}">@csrf @method('DELETE')<button class="rounded-lg border border-white/10 px-3 py-1.5 text-red-200 hover:border-red-300/60">Delete</button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-admin.table>
            <div class="mt-4">{{ $languages->links() }}</div>
        @endif
    </div>
@endsection
