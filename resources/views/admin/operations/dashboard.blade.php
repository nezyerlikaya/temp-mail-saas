@extends('layouts.admin', [
    'title' => 'Admin Operations',
    'heading' => 'Operations Center',
    'description' => 'Read-only summary of platform health, queues, cleanup, abuse, billing, and domains.',
])

@section('admin')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-admin.card title="Healthy checks" :value="$health['healthy']" tone="green">
            Warnings: {{ $health['warning'] }} · Critical: {{ $health['critical'] }}
        </x-admin.card>
        <x-admin.card title="Queue pending" :value="$queue['pending']" tone="cyan">
            Processed: {{ $queue['processed'] }} · Failed: {{ $queue['failed'] }}
        </x-admin.card>
        <x-admin.card title="Active domains" :value="$readiness['active_domains']">
            Total domains: {{ $readiness['domains'] }}
        </x-admin.card>
        <x-admin.card title="Abuse events" :value="$abuse['total']" tone="amber">
            Critical events: {{ $abuse['critical'] }}
        </x-admin.card>
        <x-admin.card title="Billing customers" :value="$billing['customers']">
            Subscriptions: {{ $billing['subscriptions'] }} · Invoices: {{ $billing['invoices'] }}
        </x-admin.card>
        <x-admin.card title="Domain health" :value="$domains['healthy']" tone="green">
            Healthy of {{ $domains['total'] }} tracked domains
        </x-admin.card>
        <x-admin.card title="Cleanup summary" :value="$cleanup?->status?->value ?? 'none'">
            Latest run: {{ $cleanup?->started_at?->diffForHumans() ?? 'No cleanup runs yet' }}
        </x-admin.card>
        <x-admin.card title="Readiness">
            Customers: {{ $readiness['billing_customers'] }} · Latest health:
            {{ $health['latest']?->checked_at?->diffForHumans() ?? 'none' }}
        </x-admin.card>
    </div>
@endsection
