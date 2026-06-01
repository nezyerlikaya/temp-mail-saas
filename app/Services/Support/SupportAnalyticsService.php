<?php

namespace App\Services\Support;

use App\Enums\SupportStatus;
use App\Models\SupportRequest;
use App\Services\Service;

final class SupportAnalyticsService extends Service
{
    public function __construct(private readonly SupportRequestService $requests) {}

    public function report(): array
    {
        return [
            ...$this->requests->metrics(),
            'total_requests' => SupportRequest::query()->count(),
            'category_distribution' => $this->counts('category'),
            'priority_distribution' => $this->counts('priority'),
            'status_distribution' => $this->counts('status'),
            'unresolved_requests' => SupportRequest::query()->whereNotIn('status', [SupportStatus::Resolved, SupportStatus::Closed])->count(),
        ];
    }

    private function counts(string $column): array
    {
        return SupportRequest::query()
            ->selectRaw("{$column}, count(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
