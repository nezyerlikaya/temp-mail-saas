<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbuseEvent;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\BillingWebhookEvent;
use App\Models\CleanupRun;
use App\Models\Domain;
use App\Models\DomainHealthCheck;
use App\Models\OperationsEvent;
use App\Models\QueueMetric;
use App\Models\SystemHealthCheck;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OperationsCenterController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.operations.dashboard', [
            'health' => [
                'healthy' => SystemHealthCheck::query()->where('status', 'healthy')->count(),
                'warning' => SystemHealthCheck::query()->where('status', 'warning')->count(),
                'critical' => SystemHealthCheck::query()->where('status', 'critical')->count(),
                'latest' => SystemHealthCheck::query()->latest('checked_at')->first(),
            ],
            'readiness' => [
                'domains' => Domain::query()->count(),
                'active_domains' => Domain::query()->where('status', 'active')->count(),
                'billing_customers' => BillingCustomer::query()->count(),
            ],
            'queue' => [
                'pending' => QueueMetric::query()->sum('pending_jobs'),
                'processed' => QueueMetric::query()->sum('processed_jobs'),
                'failed' => QueueMetric::query()->sum('failed_jobs'),
            ],
            'cleanup' => CleanupRun::query()->latest('started_at')->first(),
            'abuse' => [
                'total' => AbuseEvent::query()->count(),
                'critical' => AbuseEvent::query()->where('severity', 'critical')->count(),
            ],
            'billing' => [
                'customers' => BillingCustomer::query()->count(),
                'subscriptions' => BillingSubscription::query()->count(),
                'invoices' => BillingInvoice::query()->count(),
            ],
            'domains' => [
                'total' => Domain::query()->count(),
                'healthy' => Domain::query()->where('health_score', '>=', 80)->count(),
            ],
        ]);
    }

    public function health(): View
    {
        return view('admin.operations.health', [
            'counts' => [
                'healthy' => SystemHealthCheck::query()->where('status', 'healthy')->count(),
                'warning' => SystemHealthCheck::query()->where('status', 'warning')->count(),
                'critical' => SystemHealthCheck::query()->where('status', 'critical')->count(),
            ],
            'checks' => SystemHealthCheck::query()
                ->latest('checked_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function queue(): View
    {
        return view('admin.operations.queue', [
            'metrics' => QueueMetric::query()
                ->latest('measured_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function domains(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        return view('admin.operations.domains', [
            'search' => $search,
            'domains' => Domain::query()
                ->when($search !== '', fn ($query) => $query->where('domain', 'like', "%{$search}%"))
                ->latest('last_checked_at')
                ->paginate(15)
                ->withQueryString(),
            'checks' => DomainHealthCheck::query()
                ->latest('checked_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function abuse(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        return view('admin.operations.abuse', [
            'search' => $search,
            'events' => AbuseEvent::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where('event_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('severity', 'like', "%{$search}%");
                })
                ->latest('occurred_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function billing(): View
    {
        return view('admin.operations.billing', [
            'counts' => [
                'customers' => BillingCustomer::query()->count(),
                'subscriptions' => BillingSubscription::query()->count(),
                'invoices' => BillingInvoice::query()->count(),
            ],
            'subscriptions' => BillingSubscription::query()
                ->latest()
                ->paginate(10, ['*'], 'subscriptions_page')
                ->withQueryString(),
            'invoices' => BillingInvoice::query()
                ->latest('issued_at')
                ->paginate(10, ['*'], 'invoices_page')
                ->withQueryString(),
        ]);
    }

    public function audit(): View
    {
        return view('admin.operations.audit', [
            'cleanupRuns' => CleanupRun::query()->latest('started_at')->limit(10)->get(),
            'operationsEvents' => OperationsEvent::query()->latest('occurred_at')->limit(10)->get(),
            'billingWebhookEvents' => BillingWebhookEvent::query()->latest()->limit(10)->get(),
        ]);
    }
}
