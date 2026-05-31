@extends('layouts.admin', [
    'title' => 'Localization Center',
    'heading' => 'Localization Center',
    'description' => 'Manage active languages, translation progress, and safe localization changes.',
])

@section('admin')
    <div class="grid gap-4 md:grid-cols-3">
        <x-admin.card title="Languages" :value="$languages->count()" tone="cyan">
            Active, default, and direction-aware language records.
        </x-admin.card>
        <x-admin.card title="Active Languages" :value="$languages->where('is_active', true)->count()" tone="green">
            Only active languages are available to visitors.
        </x-admin.card>
        <x-admin.card title="Audit Events" :value="$audits->count()" tone="slate">
            Latest translation-focused changes are tracked here.
        </x-admin.card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-white">Translation Progress</h2>
                <div class="flex gap-2">
                    <a href="{{ route('admin.localization.languages') }}" class="rounded-xl border border-white/10 px-3 py-2 text-sm text-slate-200 hover:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-300">Languages</a>
                    <a href="{{ route('admin.localization.translations') }}" class="rounded-xl border border-white/10 px-3 py-2 text-sm text-slate-200 hover:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-300">Translations</a>
                    <a href="{{ route('admin.localization.import') }}" class="rounded-xl border border-white/10 px-3 py-2 text-sm text-slate-200 hover:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-300">Import</a>
                </div>
            </div>

            @if ($languages->isEmpty())
                <x-admin.empty-state message="No languages have been created yet." />
            @else
                <x-admin.table :headers="['Language', 'Status', 'Direction', 'Progress']">
                    @foreach ($languages as $language)
                        @php($summary = $progress[$language->id] ?? ['percent' => 0, 'completed' => 0, 'total' => 0])
                        <tr>
                            <td class="px-4 py-3 text-sm text-white">
                                <div class="font-medium">{{ $language->name }}</div>
                                <div class="text-xs text-slate-400">{{ $language->code }} / {{ $language->native_name }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $language->is_active ? 'bg-emerald-400/10 text-emerald-200' : 'bg-slate-400/10 text-slate-300' }}">
                                    {{ $language->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if ($language->is_default)
                                    <span class="ml-2 rounded-full bg-cyan-400/10 px-2.5 py-1 text-xs font-medium text-cyan-200">Default</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-300">{{ strtoupper($language->direction->value) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-300">
                                <div class="flex items-center gap-3">
                                    <div class="h-2 w-32 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                                        <div class="h-full rounded-full bg-cyan-300" style="width: {{ $summary['percent'] }}%"></div>
                                    </div>
                                    <span>{{ $summary['percent'] }}%</span>
                                    <span class="text-xs text-slate-500">{{ $summary['completed'] }}/{{ $summary['total'] }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-white">Latest Audit</h2>
            @if ($audits->isEmpty())
                <x-admin.empty-state message="No localization audit events yet." />
            @else
                <div class="space-y-3">
                    @foreach ($audits as $audit)
                        <article class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
                            <div class="text-sm font-medium text-white">{{ $audit->action }}</div>
                            <div class="mt-1 text-xs text-slate-400">{{ $audit->key ?? 'Language change' }}</div>
                            <time class="mt-2 block text-xs text-slate-500" datetime="{{ $audit->created_at?->toISOString() }}">
                                {{ $audit->created_at?->diffForHumans() }}
                            </time>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
