<?php

namespace App\Services\System;

use App\Services\Operations\IncidentService;
use App\Services\Operations\MonitoringService;
use App\Services\Service;
use Illuminate\Support\Facades\File;

final class SupportReadinessService extends Service
{
    public function __construct(
        private readonly MonitoringService $monitoring,
        private readonly IssueTriageService $triage,
    ) {}

    public function report(): array
    {
        $monitoring = $this->monitoring->summary();
        $checks = [
            $this->check('incident_process', ! (bool) config('production.public_beta.support.incident_process_required', true) || class_exists(IncidentService::class), 'Incident process is available.', 'Incident process is not available.'),
            $this->check('monitoring_process', ! (bool) config('production.public_beta.support.monitoring_process_required', true) || (bool) config('monitoring.enabled', true), 'Monitoring process is enabled.', 'Monitoring process is disabled.', 'warning'),
            $this->check('runbooks', (bool) config('production.public_beta.support.runbooks_documented', true) && File::exists(base_path('docs/deployment/public-beta.md')), 'Public beta runbooks are documented.', 'Public beta runbooks need documentation.', 'warning'),
            $this->check('escalation_paths', (bool) config('production.public_beta.support.escalation_paths_documented', true), 'Escalation paths are documented.', 'Escalation paths need documentation.', 'warning'),
            $this->check('troubleshooting_guidance', (bool) config('production.public_beta.support.troubleshooting_guidance_documented', true), 'Troubleshooting guidance is documented.', 'Troubleshooting guidance needs documentation.', 'warning'),
            $this->check('critical_incident_queue', (int) ($monitoring['critical_incidents'] ?? 0) === 0, 'No critical incidents are open.', 'Critical incidents need review.'),
            $this->check('triage_framework', $this->triage->classify('critical', 'platform')['priority'] === 1, 'Issue triage framework is available.', 'Issue triage framework needs review.'),
        ];

        return $this->section($checks);
    }

    private function section(array $checks): array
    {
        $blockers = collect($checks)->where('status', 'blocked')->values()->all();
        $warnings = collect($checks)->where('status', 'warning')->values()->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'checks' => $checks,
            'passed' => collect($checks)->where('status', 'passed')->values()->all(),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $this->recommendations($checks),
        ];
    }

    private function recommendations(array $checks): array
    {
        return collect($checks)
            ->reject(fn (array $check): bool => $check['passed'])
            ->map(fn (array $check): string => $check['message'])
            ->push('Review support coverage before opening beta access.')
            ->unique()
            ->values()
            ->all();
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $failedStatus = 'blocked'): array
    {
        return [
            'category' => 'support',
            'name' => $name,
            'passed' => $passed,
            'status' => $passed ? 'passed' : $failedStatus,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }
}
