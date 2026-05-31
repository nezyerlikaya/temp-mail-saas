@extends('layouts.admin', ['title' => 'Queue Center', 'heading' => 'Queue Center', 'description' => 'Read-only queue metric visibility.'])

@section('admin')
    @if ($metrics->isEmpty())
        <x-admin.empty-state message="No queue metrics recorded yet." />
    @else
        <x-admin.table :headers="['Queue', 'Pending', 'Processed', 'Failed', 'Measured']">
            @foreach ($metrics as $metric)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $metric->queue_name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $metric->pending_jobs }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $metric->processed_jobs }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $metric->failed_jobs }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $metric->measured_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
        <div class="mt-4">{{ $metrics->links() }}</div>
    @endif
@endsection
