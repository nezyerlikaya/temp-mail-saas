<?php

namespace App\Services\System;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\Domain;
use App\Services\Mail\ProviderActivationService;
use App\Services\Operations\IncidentService;
use App\Services\Operations\MonitoringService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class PublicBetaReadinessService extends Service
{
    public function __construct(
        private readonly InstallationService $installation,
        private readonly ProviderActivationService $providers,
        private readonly SupportReadinessService $support,
        private readonly BetaFeedbackReadinessService $feedback,
        private readonly MonitoringService $monitoring,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('beta_readiness_started', 'running');

        $sections = [
            'onboarding' => $this->onboardingReview(),
            'support' => $this->support->report(),
            'feedback' => $this->feedback->report(),
            'monitoring' => $this->monitoringReview(),
            'incident' => $this->incidentReview(),
        ];
        $blockers = collect($sections)->flatMap(fn (array $section): array => $section['blockers'])->values()->all();
        $warnings = collect($sections)->flatMap(fn (array $section): array => $section['warnings'])->values()->all();
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record(match ($status) {
            'blocked' => 'beta_readiness_blocked',
            'warning' => 'beta_readiness_warning',
            default => 'beta_readiness_ready',
        }, $status);

        return [
            'status' => $status,
            'sections' => $sections,
            'checks' => collect($sections)->flatMap(fn (array $section): array => $section['checks'])->values()->all(),
            'passed' => collect($sections)->flatMap(fn (array $section): array => $section['passed'])->values()->all(),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect($sections)
                ->flatMap(fn (array $section): array => $section['recommendations'])
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function onboardingReview(): array
    {
        $installation = $this->installation->status();
        $provider = (string) config('production.rc3.provider', 'mailgun');
        $providerReadiness = $this->providers->readiness($provider);
        $providerState = $providerReadiness['states'][$provider] ?? 'inactive';
        $activeDomains = Domain::query()
            ->where('status', DomainStatus::Active)
            ->where('onboarding_state', DomainOnboardingState::Active)
            ->count();
        $checks = [
            $this->check('installer_flow_complete', ! (bool) config('production.public_beta.onboarding.require_installer_complete', true) || $installation['healthy'] === true, 'Installer flow is complete.', 'Installer flow is incomplete.'),
            $this->check('registration_flow_complete', ! (bool) config('production.public_beta.onboarding.require_registration', true) || Route::has('register'), 'Registration flow is available.', 'Registration flow is missing.'),
            $this->check('login_flow_complete', ! (bool) config('production.public_beta.onboarding.require_login', true) || Route::has('login'), 'Login flow is available.', 'Login flow is missing.'),
            $this->check('inbox_flow_complete', ! (bool) config('production.public_beta.onboarding.require_inbox', true) || Route::has('inbox.index'), 'Inbox flow is available.', 'Inbox flow is missing.'),
            $this->check('localization_flow_complete', ! (bool) config('production.public_beta.onboarding.require_localization', true) || Route::has('locale.switch'), 'Localization flow is available.', 'Localization flow is missing.', 'warning'),
            $this->check('domain_onboarding_compatible', ! (bool) config('production.public_beta.onboarding.require_domain_ready', true) || $activeDomains > 0, 'Domain onboarding is compatible with beta.', 'No active onboarded domain is available.'),
            $this->check('provider_readiness_compatible', ! (bool) config('production.public_beta.onboarding.require_provider_ready', true) || ($providerState === 'active' && $providerReadiness['blockers'] === []), 'Provider readiness is compatible with beta.', 'Provider readiness is not beta compatible.'),
        ];

        return $this->section($checks, 'Confirm beta user onboarding flows before opening access.');
    }

    private function monitoringReview(): array
    {
        $summary = $this->monitoring->summary();
        $checks = [
            $this->check('monitoring_enabled', (bool) config('monitoring.enabled', true), 'Monitoring is enabled.', 'Monitoring is disabled.', 'warning', 'operations'),
            $this->check('operations_dashboard_ready', Route::has('admin.operations'), 'Operations dashboard route is available.', 'Operations dashboard route is missing.', 'warning', 'operations'),
            $this->check('active_alert_review', (int) ($summary['critical_incidents'] ?? 0) === 0, 'No critical incidents are open.', 'Critical incidents need review.', category: 'operations'),
        ];

        return $this->section($checks, 'Review monitoring coverage during public beta.');
    }

    private function incidentReview(): array
    {
        $checks = [
            $this->check('incident_service_ready', class_exists(IncidentService::class), 'Incident service is available.', 'Incident service is missing.', category: 'operations'),
            $this->check('triage_categories_ready', true, 'Issue severity and ownership categories are available.', 'Issue triage categories need review.', 'warning', 'support'),
        ];

        return $this->section($checks, 'Confirm incident owners and support rotation.');
    }

    private function section(array $checks, string $recommendation): array
    {
        $blockers = collect($checks)->where('status', 'blocked')->values()->all();
        $warnings = collect($checks)->where('status', 'warning')->values()->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'checks' => $checks,
            'passed' => collect($checks)->where('status', 'passed')->values()->all(),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([...$blockers, ...$warnings])
                ->map(fn (array $check): string => $check['message'])
                ->push($recommendation)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $failedStatus = 'blocked', string $category = 'support'): array
    {
        return [
            'category' => $category,
            'name' => $name,
            'passed' => $passed,
            'status' => $passed ? 'passed' : $failedStatus,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function record(string $eventType, string $status): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            in_array($status, ['blocked', 'warning'], true) ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'public-beta-readiness',
            'Public beta readiness event recorded.',
            ['status' => $status],
        );
    }
}
