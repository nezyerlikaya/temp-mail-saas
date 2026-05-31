@extends('layouts.admin', ['title' => 'Abuse Center', 'heading' => 'Abuse Center', 'description' => 'Read-only abuse event visibility without raw hashes.'])

@section('admin')
    <form method="GET" class="mb-4" role="search">
        <label for="abuse-search" class="sr-only">Search abuse events</label>
        <input id="abuse-search" name="search" value="{{ $search }}" placeholder="Search event type, severity, or status" class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30">
    </form>

    @if ($events->isEmpty())
        <x-admin.empty-state message="No abuse events found." />
    @else
        <x-admin.table :headers="['Type', 'Severity', 'Status', 'Risk', 'Occurred']">
            @foreach ($events as $event)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $event->event_type->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->severity->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->status->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->risk_score }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $event->occurred_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
@endsection
