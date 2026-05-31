@extends('layouts.admin', ['title' => 'Domain Center', 'heading' => 'Domain Center', 'description' => 'Read-only domain pool and health visibility.'])

@section('admin')
    <form method="GET" class="mb-4" role="search">
        <label for="domain-search" class="sr-only">Search domains</label>
        <input id="domain-search" name="search" value="{{ $search }}" placeholder="Search domains" class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30">
    </form>

    @if ($domains->isEmpty())
        <x-admin.empty-state message="No domains found." />
    @else
        <x-admin.table :headers="['Domain', 'Status', 'Tier', 'Health score', 'Last checked']">
            @foreach ($domains as $domain)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $domain->domain }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $domain->status->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $domain->tier->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $domain->health_score }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $domain->last_checked_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
        <div class="mt-4">{{ $domains->links() }}</div>
    @endif
@endsection
