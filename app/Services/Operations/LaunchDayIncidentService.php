<?php

namespace App\Services\Operations;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Services\Service;

final class LaunchDayIncidentService extends Service
{
    public function review(): array
    {
        $incidents = Incident::query()
            ->where('status', '!=', IncidentStatus::Resolved->value)
            ->get();
        $critical = $incidents->where('severity', IncidentSeverity::Critical);
        $high = $incidents->where('severity', IncidentSeverity::High);
        $status = $critical->isNotEmpty() ? 'critical' : ($high->isNotEmpty() || $incidents->isNotEmpty() ? 'warning' : 'healthy');

        return [
            'status' => $status,
            'open_count' => $incidents->count(),
            'critical_count' => $critical->count(),
            'high_count' => $high->count(),
            'categories' => $this->categories($incidents),
            'escalation_recommendations' => $this->escalation($critical->count(), $high->count()),
            'rollback_recommendations' => $critical->isNotEmpty()
                ? ['Review rollback triggers immediately for critical launch-day incidents.']
                : [],
        ];
    }

    private function categories(mixed $incidents): array
    {
        $allowed = ['provider', 'queue', 'domain', 'inbox', 'billing', 'api', 'operations'];

        return collect($allowed)
            ->mapWithKeys(fn (string $category): array => [$category => $incidents->where('category', $category)->count()])
            ->all();
    }

    private function escalation(int $critical, int $high): array
    {
        $recommendations = [];

        if ($critical >= (int) config('production.first_24_hours.escalation.critical_incidents', 1)) {
            $recommendations[] = 'Escalate to launch commander for critical incident ownership.';
        }

        if ($high >= (int) config('production.first_24_hours.escalation.high_incidents', 1)) {
            $recommendations[] = 'Escalate high severity incidents to category owner.';
        }

        return $recommendations;
    }
}
