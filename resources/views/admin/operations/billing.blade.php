@extends('layouts.admin', ['title' => 'Billing Center', 'heading' => 'Billing Center', 'description' => 'Read-only billing summaries without payment or card data.'])

@section('admin')
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-admin.card title="Customers" :value="$counts['customers']" />
        <x-admin.card title="Subscriptions" :value="$counts['subscriptions']" />
        <x-admin.card title="Invoices" :value="$counts['invoices']" />
    </div>

    <h2 class="mb-3 text-lg font-semibold text-white">Subscriptions</h2>
    @if ($subscriptions->isEmpty())
        <x-admin.empty-state message="No subscriptions found." />
    @else
        <x-admin.table :headers="['Provider', 'Status', 'Interval', 'Current period ends']">
            @foreach ($subscriptions as $subscription)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $subscription->provider->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $subscription->status->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $subscription->interval }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $subscription->current_period_ends_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
        <div class="mt-4">{{ $subscriptions->links() }}</div>
    @endif

    <h2 class="mb-3 mt-8 text-lg font-semibold text-white">Invoices</h2>
    @if ($invoices->isEmpty())
        <x-admin.empty-state message="No invoices found." />
    @else
        <x-admin.table :headers="['Provider', 'Status', 'Currency', 'Due', 'Paid', 'Issued']">
            @foreach ($invoices as $invoice)
                <tr>
                    <td class="px-4 py-3 text-sm text-white">{{ $invoice->provider->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $invoice->status->value }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ strtoupper($invoice->currency) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $invoice->amount_due }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300">{{ $invoice->amount_paid }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ $invoice->issued_at?->toDayDateTimeString() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif
@endsection
