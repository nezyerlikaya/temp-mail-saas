<?php

namespace App\Services\System;

use App\Services\Mail\ProviderActivationService;
use App\Services\Operations\MonitoringService;
use App\Services\Service;

final class V1LaunchCertificationService extends Service
{
    public function __construct(
        private readonly RC3CertificationService $rc3,
        private readonly PublicBetaCertificationService $beta,
        private readonly ProductionLoadValidationService $load,
        private readonly GoLiveStatusService $goLive,
        private readonly LaunchSignOffService $signOff,
        private readonly PostLaunchMonitoringService $postLaunch,
        private readonly ProviderActivationService $providers,
        private readonly MonitoringService $monitoring,
    ) {}

    public function report(): array
    {
        $sections = [
            'rc3' => $this->normalize($this->rc3->report()),
            'public_beta' => $this->normalize($this->beta->report()),
            'load' => $this->normalize($this->load->report()),
            'go_live' => $this->normalize($this->goLive->evaluate(), 'state'),
            'sign_off' => $this->normalize($this->signOff->checklist(), 'status'),
            'post_launch_monitoring' => $this->normalize($this->postLaunch->plan()),
            'provider' => $this->providerSection(),
            'monitoring' => $this->monitoringSection(),
        ];
        $blockers = collect($sections)->flatMap(fn (array $section): array => $section['blockers'])->values()->all();
        $warnings = collect($sections)->flatMap(fn (array $section): array => $section['warnings'])->values()->all();
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified');

        return [
            'target' => (string) config('production.v1_launch.target', 'v1.0.0'),
            'status' => $status,
            'summary' => $this->summary($status, $blockers, $warnings),
            'sections' => $sections,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $this->recommendations($sections),
        ];
    }

    private function providerSection(): array
    {
        $provider = (string) config('production.rc3.provider', 'mailgun');
        $readiness = $this->providers->readiness($provider);
        $blockers = $readiness['blockers'] === [] ? [] : [[
            'category' => 'provider',
            'name' => 'provider_readiness',
            'message' => 'Provider readiness has blockers.',
        ]];

        return [
            'status' => $blockers === [] ? 'certified' : 'blocked',
            'blockers' => $blockers,
            'warnings' => [],
            'recommendations' => [],
        ];
    }

    private function monitoringSection(): array
    {
        $summary = $this->monitoring->summary();
        $blockers = ((int) ($summary['critical_incidents'] ?? 0)) === 0 ? [] : [[
            'category' => 'operations',
            'name' => 'critical_incident_review',
            'message' => 'Critical incidents must be resolved before launch.',
        ]];

        return [
            'status' => $blockers === [] ? 'certified' : 'blocked',
            'blockers' => $blockers,
            'warnings' => [],
            'recommendations' => [],
        ];
    }

    private function normalize(array $report, string $statusKey = 'status'): array
    {
        $status = (string) ($report[$statusKey] ?? 'ready');
        $blockers = $report['blockers'] ?? [];
        $warnings = $report['warnings'] ?? [];

        if (in_array($status, ['blocked'], true) && $blockers === []) {
            $blockers = [['category' => 'operations', 'name' => 'readiness_status', 'message' => 'Readiness status is blocked.']];
        }

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $report['recommendations'] ?? [],
        ];
    }

    private function recommendations(array $sections): array
    {
        return collect($sections)
            ->flatMap(fn (array $section): array => $section['recommendations'])
            ->push('Launch only after blockers are resolved, warnings are accepted, and rollback owners are present.')
            ->unique()
            ->values()
            ->all();
    }

    private function summary(string $status, array $blockers, array $warnings): string
    {
        return match ($status) {
            'blocked' => 'v1.0.0 launch is blocked by '.count($blockers).' blocker(s).',
            'warning' => 'v1.0.0 launch requires review of '.count($warnings).' warning(s).',
            default => 'Temp Mail SaaS v1.0.0 is production launch ready.',
        };
    }
}
