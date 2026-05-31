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
use App\Services\System\PerformanceCacheService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OperationsCenterController extends Controller
{
    public function dashboard(PerformanceCacheService $cache): View
    {
        return view('admin.operations.dashboard', $cache->operationsDashboard(fn (): array => [
            'health' => [
                ...$this->statusCounts(SystemHealthCheck::query(), ['healthy', 'warning', 'critical']),
                'latest' => SystemHealthCheck::query()->latest('checked_at')->first(),
            ],
            'readiness' => [
                ...$this->domainReadinessCounts(),
                'billing_customers' => BillingCustomer::query()->count(),
            ],
            'queue' => $this->queueSummary(),
            'cleanup' => CleanupRun::query()->latest('started_at')->first(),
            'abuse' => $this->abuseSummary(),
            'billing' => [
                'customers' => BillingCustomer::query()->count(),
                'subscriptions' => BillingSubscription::query()->count(),
                'invoices' => BillingInvoice::query()->count(),
            ],
            'domains' => $this->domainHealthSummary(),
        ]));
    }

    public function health(): View
    {
        return view('admin.operations.health', [
            'counts' => [
                ...$this->statusCounts(SystemHealthCheck::query(), ['healthy', 'warning', 'critical']),
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
                ->limit((int) config('performance.aggregation.domain_health_recent_limit', 5))
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
        $limit = (int) config('performance.aggregation.recent_audit_limit', 10);

        return view('admin.operations.audit', [
            'cleanupRuns' => CleanupRun::query()->latest('started_at')->limit($limit)->get(),
            'operationsEvents' => OperationsEvent::query()->latest('occurred_at')->limit($limit)->get(),
            'billingWebhookEvents' => BillingWebhookEvent::query()->latest()->limit($limit)->get(),
        ]);
    }

    private function statusCounts($query, array $statuses): array
    {
        $counts = $query
            ->selectRaw('status, count(*) as aggregate')
            ->whereIn('status', $statuses)
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect($statuses)
            ->mapWithKeys(fn (string $status): array => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    private function queueSummary(): array
    {
        $summary = QueueMetric::query()
            ->selectRaw('coalesce(sum(pending_jobs), 0) as pending, coalesce(sum(processed_jobs), 0) as processed, coalesce(sum(failed_jobs), 0) as failed')
            ->first();

        return [
            'pending' => (int) ($summary->pending ?? 0),
            'processed' => (int) ($summary->processed ?? 0),
            'failed' => (int) ($summary->failed ?? 0),
        ];
    }

    private function abuseSummary(): array
    {
        $summary = AbuseEvent::query()
            ->selectRaw('count(*) as total, sum(case when severity = ? then 1 else 0 end) as critical', ['critical'])
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'critical' => (int) ($summary->critical ?? 0),
        ];
    }

    private function domainReadinessCounts(): array
    {
        $summary = Domain::query()
            ->selectRaw('count(*) as domains, sum(case when status = ? then 1 else 0 end) as active_domains', ['active'])
            ->first();

        return [
            'domains' => (int) ($summary->domains ?? 0),
            'active_domains' => (int) ($summary->active_domains ?? 0),
        ];
    }

    private function domainHealthSummary(): array
    {
        $summary = Domain::query()
            ->selectRaw('count(*) as total, sum(case when health_score >= ? then 1 else 0 end) as healthy', [80])
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'healthy' => (int) ($summary->healthy ?? 0),
        ];
    }
}
