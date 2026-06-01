<?php

namespace App\Services\Roadmap;

use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class DashboardUsabilityReviewService extends Service
{
    public function review(): array
    {
        $checks = [
            $this->check('dashboard_information_density', Route::has('admin.index') && (bool) config('admin-roadmap.dashboard_usability.information_density_ready', true), 'Dashboard information density is ready for planning review.', 'Dashboard information density needs review.', 'warning'),
            $this->check('kpi_visibility', Route::has('admin.operations') && (bool) config('admin-roadmap.dashboard_usability.kpi_visibility_ready', true), 'KPI visibility is ready for planning review.', 'KPI visibility needs review.', 'warning'),
            $this->check('operational_awareness', Route::has('admin.health') && Route::has('admin.queue') && (bool) config('admin-roadmap.dashboard_usability.operational_awareness_ready', true), 'Operational awareness signals are available.', 'Operational awareness needs review.', 'warning'),
            $this->check('quick_action_readiness', (bool) config('admin-roadmap.dashboard_usability.quick_action_ready', true), 'Quick-action readiness is ready for planning.', 'Quick-action readiness needs review.', 'warning'),
        ];

        return $this->summarize($checks);
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return ['name' => $name, 'passed' => $passed, 'classification' => $passed ? 'passed' : $classification, 'message' => $passed ? $passedMessage : $failedMessage];
    }

    private function summarize(array $checks): array
    {
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = collect($checks)->where('classification', 'warning')->values()->all();

        return [
            'state' => $blockers !== [] ? 'improvement-needed' : ($warnings !== [] ? 'acceptable' : 'excellent'),
            'warnings' => $warnings,
            'blockers' => $blockers,
            'recommendations' => collect($checks)->reject(fn (array $check): bool => $check['passed'])->pluck('message')->values()->all(),
            'checks' => $checks,
        ];
    }
}
