@extends('layouts.admin', ['title' => 'Health Center', 'heading' => 'Health Center', 'description' => 'Latest system health checks and status counts.'])

@section('admin')
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-admin.card title="Healthy" :value="$counts['healthy']" tone="green" />
        <x-admin.card title="Warning" :value="$counts['warning']" tone="amber" />
        <x-admin.card title="Critical" :value="$counts['critical']" tone="red" />
    </div>

    @if ($checks->isEmpty())
        <x-admin.empty-state message="No health checks recorded yet." />
    @else
        <x-admin.table :headers="['Check', 'Status', 'Message', 'Checked']">
            @foreach ($checks as $check)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $check->check_name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $check->status->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $check->message ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $check->checked_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
        <div class="mt-4">{{ $checks->links() }}</div>
    @endif
@endsection
