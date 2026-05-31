@extends('layouts.admin', ['title' => 'Audit Center', 'heading' => 'Audit Center', 'description' => 'Read-only audit aggregation across cleanup, operations, and billing webhooks.'])

@section('admin')
    <h2 class="mb-3 text-lg font-semibold text-white">Cleanup runs</h2>
    @if ($cleanupRuns->isEmpty())
        <x-admin.empty-state message="No cleanup runs recorded." />
    @else
        <x-admin.table :headers="['Type', 'Status', 'Dry run', 'Started', 'Finished']">
            @foreach ($cleanupRuns as $run)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $run->type->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $run->status->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $run->dry_run ? 'yes' : 'no' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $run->started_at?->toDayDateTimeString() ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $run->finished_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    @endif

    <h2 class="mb-3 mt-8 text-lg font-semibold text-white">Operations events</h2>
    @if ($operationsEvents->isEmpty())
        <x-admin.empty-state message="No operations events recorded." />
    @else
        <x-admin.table :headers="['Category', 'Type', 'Severity', 'Status', 'Occurred']">
            @foreach ($operationsEvents as $event)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $event->category->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->event_type }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->severity->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->status->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $event->occurred_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    @endif

    <h2 class="mb-3 mt-8 text-lg font-semibold text-white">Billing webhook events</h2>
    @if ($billingWebhookEvents->isEmpty())
        <x-admin.empty-state message="No billing webhook events recorded." />
    @else
        <x-admin.table :headers="['Provider', 'Event', 'Signature', 'Status', 'Processed']">
            @foreach ($billingWebhookEvents as $event)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $event->provider->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->event_type }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->signature_valid ? 'valid' : 'invalid' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $event->status->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $event->processed_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    @endif
@endsection
