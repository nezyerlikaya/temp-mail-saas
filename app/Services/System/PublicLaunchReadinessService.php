<?php

namespace App\Services\System;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Domain\LiveDomainReadinessService;
use App\Services\Mail\LiveProviderReadinessService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;

final class PublicLaunchReadinessService extends Service
{
    public function __construct(
        private readonly ProductionDeploymentReadinessService $production,
        private readonly LiveProviderReadinessService $providers,
        private readonly LiveDomainReadinessService $domains,
        private readonly SupportReadinessService $support,
        private readonly PublicTrafficReadinessService $traffic,
        private readonly LaunchOperationsCertificationService $certification,
        private readonly LaunchGateService $gates,
        private readonly PostLaunchObservationService $observation,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('public_launch_review_started');

        $provider = (string) config('production.public_launch.provider', 'mailgun');
        $domain = config('production.public_launch.domain');
        $production = $this->production->report();
        $providers = $this->providers->report($provider);
        $domains = $this->domains->report(is_string($domain) ? $domain : null);
        $support = $this->support->report();
        $traffic = $this->traffic->report();
        $certification = $this->certification->certify();
        $gateSections = [
            'security' => $this->securityGate(),
            'providers' => $providers,
            'domains' => $domains,
            'queue' => $traffic,
            'billing' => $certification['monitoring']['sections']['billing'],
            'api' => $certification['monitoring']['sections']['api'],
            'operations' => $certification,
            'support' => $support,
        ];
        $gates = $this->gates->evaluate($gateSections);
        $blockers = [
            ...($production['status'] === 'blocked' ? [$this->issue('production', 'production_readiness', 'Production deployment readiness is blocked.')] : []),
            ...($traffic['status'] === 'blocked' ? [$this->issue('traffic', 'public_traffic', 'Public traffic readiness is blocked.')] : []),
            ...($certification['status'] === 'blocked' ? [$this->issue('operations', 'launch_certification', 'Launch operations certification is blocked.')] : []),
            ...$gates['blockers'],
        ];
        $warnings = [
            ...($production['status'] === 'warning' ? [$this->issue('production', 'production_readiness', 'Production deployment readiness has warnings.')] : []),
            ...($certification['status'] === 'warning' ? [$this->issue('operations', 'launch_certification', 'Launch operations certification has warnings.')] : []),
            ...$gates['warnings'],
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record('public_launch_review_'.$status, $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
        ]);

        if ($status === 'ready') {
            $this->record('public_launch_certified');
        }

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([...$blockers, ...$warnings])->pluck('message')->unique()->values()->all(),
            'certification' => $certification,
            'gates' => $gates,
            'observation' => $this->observation->plan(),
            'sections' => compact('production', 'providers', 'domains', 'support', 'traffic'),
        ];
    }

    private function securityGate(): array
    {
        $checks = [
            $this->check('app_debug_disabled', ! (bool) config('app.debug', false), 'APP_DEBUG is disabled.', 'APP_DEBUG must be disabled.'),
            $this->check('app_key_present', filled((string) config('app.key')), 'APP_KEY is configured.', 'APP_KEY is missing.'),
            $this->check('abuse_protection_enabled', (bool) config('abuse.enabled', true), 'Abuse protection is enabled.', 'Abuse protection must be enabled.'),
        ];

        return [
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'warnings' => [],
            'checks' => $checks,
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : 'blocker',
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function issue(string $category, string $name, string $message): array
    {
        return compact('category', 'name', 'message');
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'public-launch-readiness',
            'Public production launch readiness event recorded.',
            $metadata,
        );
    }
}
