<?php

namespace App\Services\Operations;

use App\Services\Service;

final class LaunchMonitoringSummaryService extends Service
{
    public function summarize(array $monitoring): array
    {
        return [
            'status' => $monitoring['status'],
            'incident_status' => $monitoring['incidents']['status'],
            'rollback_status' => $monitoring['rollback']['status'],
            'critical_count' => count($monitoring['critical']),
            'warning_count' => count($monitoring['warnings']),
            'recommendations' => $this->recommendations($monitoring),
        ];
    }

    private function recommendations(array $monitoring): array
    {
        return collect([...$monitoring['critical'], ...$monitoring['warnings']])
            ->pluck('message')
            ->merge($monitoring['incidents']['escalation_recommendations'])
            ->merge($monitoring['incidents']['rollback_recommendations'])
            ->unique()
            ->values()
            ->all();
    }
}
