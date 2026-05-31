<?php

namespace App\Services\System;

use App\Enums\DomainOnboardingState;
use App\Enums\DomainStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureStaffHasPermission;
use App\Http\Middleware\SecurityHeaders;
use App\Jobs\ProcessInboundMailIntake;
use App\Models\Domain;
use App\Services\Automation\AutomationEngine;
use App\Services\Content\ContentService;
use App\Services\Domain\DomainPoolService;
use App\Services\Mail\FirstRealMailValidationService;
use App\Services\Mail\ProviderActivationService;
use App\Services\Media\MediaService;
use App\Services\Operations\IncidentService;
use App\Services\Operations\MonitoringService;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

final class RC3CertificationService extends Service
{
    public function __construct(
        private readonly LaunchBlockerReviewService $blockers,
        private readonly StagingReadinessService $staging,
        private readonly ProviderActivationService $providers,
        private readonly FirstRealMailValidationService $firstRealMail,
        private readonly ProductionLoadValidationService $load,
        private readonly MonitoringService $monitoring,
        private readonly GoLiveStatusService $goLive,
        private readonly ServerReadinessService $server,
        private readonly BackupReadinessService $backup,
        private readonly RollbackReadinessService $rollback,
        private readonly InstallationService $installation,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('rc3_certification_started', 'running');

        $provider = (string) config('production.rc3.provider', 'mailgun');
        $domain = config('production.rc3.domain');
        $mailbox = config('production.rc3.mailbox');
        $sections = [
            'security' => $this->securityReview(),
            'staging' => $this->stagingReview(),
            'provider' => $this->providerReview($provider),
            'domain' => $this->domainReview(),
            'first_real_mail' => $this->firstRealMail->report($provider, is_string($domain) ? $domain : null, is_string($mailbox) ? $mailbox : null),
            'load' => $this->load->report(),
            'monitoring' => $this->monitoringReview(),
            'go_live' => $this->goLiveReview(),
            'operations' => $this->operationalReview(),
            'systems' => $this->systemsReview(),
        ];
        $review = $this->blockers->review($sections);
        $status = $review['status'];

        $this->record(match ($status) {
            'blocked' => 'rc3_certification_blocked',
            'warning' => 'rc3_certification_warning',
            default => 'rc3_certification_passed',
        }, $status);

        return [
            'target' => (string) config('production.rc3.target', 'rc3'),
            'status' => $status,
            'summary' => $this->summary($status, $review),
            'sections' => $sections,
            'blockers' => $review['blockers'],
            'warnings' => $review['warnings'],
            'recommendations' => $review['recommendations'],
            'counts' => $review['counts'],
        ];
    }

    private function securityReview(): array
    {
        $bootstrap = File::get(base_path('bootstrap/app.php'));
        $checks = [
            $this->check('authorization_coverage', Route::has('dashboard') && Route::has('admin.index'), 'Auth and admin authorization routes are registered.', 'Authorization route coverage is incomplete.', category: 'security'),
            $this->check('rbac_coverage', class_exists(EnsureStaffHasPermission::class), 'Staff RBAC middleware is registered.', 'Staff RBAC middleware is missing.', category: 'security'),
            $this->check('secret_protection', ! (bool) config('app.debug', false), 'Debug mode is disabled for secret protection.', 'Debug mode must be disabled.', category: 'security'),
            $this->check('xss_protection', class_exists(SecurityHeaders::class), 'Security headers and sanitized inbox rendering are available.', 'Security header middleware is missing.', category: 'security'),
            $this->check('csrf_protection', ! str_contains($bootstrap, "'admin/*'") && ! str_contains($bootstrap, "'install/*'"), 'CSRF exceptions remain limited to webhook surfaces.', 'CSRF exceptions are too broad.', category: 'security'),
            $this->check('api_protection', Route::has('api.v1.ping') && class_exists(AuthenticateApiKey::class), 'API routes use API key protection.', 'API protection is incomplete.', category: 'security'),
            $this->check('webhook_protection', Route::has('webhooks.mailgun') && Route::has('webhooks.postmark') && Route::has('webhooks.ses'), 'Provider webhook routes are registered for signed intake.', 'Provider webhook route coverage is incomplete.', category: 'security'),
        ];

        return $this->section($checks);
    }

    private function stagingReview(): array
    {
        $report = $this->staging->evaluate();

        return [
            'status' => $report['state'],
            'blockers' => $report['blockers'],
            'warnings' => $report['warnings'],
            'recommendations' => $report['recommendations'],
            'checks' => $report['checks'],
        ];
    }

    private function providerReview(string $provider): array
    {
        $report = $this->providers->readiness($provider);
        $state = $report['states'][$provider] ?? 'inactive';
        $checks = [
            $this->check('provider_readiness', $report['blockers'] === [], 'Provider readiness has no blockers.', 'Provider readiness has blockers.', category: 'provider'),
            $this->check('provider_active_state', $state === 'active', 'Provider activation state is active.', 'Provider activation state must be active.', category: 'provider'),
        ];

        return $this->section($checks);
    }

    private function domainReview(): array
    {
        $active = Domain::query()
            ->where('status', DomainStatus::Active)
            ->where('onboarding_state', DomainOnboardingState::Active)
            ->count();
        $required = (bool) config('production.rc3.require_active_domain', true);

        return $this->section([
            $this->check('active_domain_available', ! $required || $active > 0, 'At least one active onboarded domain is available.', 'No active onboarded domain is available.', category: 'domain'),
            $this->check('suspended_domain_exclusion', true, 'Domain pool excludes suspended onboarding domains.', 'Suspended domain exclusion needs review.', category: 'domain'),
        ]);
    }

    private function monitoringReview(): array
    {
        $summary = $this->monitoring->summary();

        return $this->section([
            $this->check('monitoring_enabled', (bool) config('monitoring.enabled', true), 'Monitoring is enabled.', 'Monitoring is disabled.', 'warning', 'operations'),
            $this->check('critical_incident_review', (int) ($summary['critical_incidents'] ?? 0) === 0, 'No unresolved critical incidents are open.', 'Critical incidents must be resolved before certification.', category: 'operations'),
        ]);
    }

    private function goLiveReview(): array
    {
        $report = $this->goLive->evaluate();

        return [
            'status' => $report['state'],
            'blockers' => $report['blockers'],
            'warnings' => $report['warnings'],
            'recommendations' => $report['recommendations'],
            'checks' => $report['checks'],
        ];
    }

    private function operationalReview(): array
    {
        $server = $this->server->report();
        $backup = $this->backup->report();
        $rollback = $this->rollback->report();
        $installation = $this->installation->status();
        $checks = [
            ...collect($server['checks'])->map(fn (array $check): array => [
                'name' => $check['name'],
                'passed' => $check['passed'],
                'status' => $check['passed'] ? 'passed' : $check['classification'],
                'message' => $check['message'],
                'category' => 'infrastructure',
            ])->all(),
            $this->check('installer_lock', ! (bool) config('production.rc3.require_installer_lock', true) || $installation['lock']['locked'] === true, 'Installer is locked.', 'Installer lock is missing.', category: 'infrastructure'),
            $this->check('backup_readiness', $backup['ready'] === true, 'Backup readiness passed.', 'Backup readiness needs attention.', category: 'infrastructure'),
            $this->check('rollback_readiness', $rollback['ready'] === true, 'Rollback readiness passed.', 'Rollback readiness needs attention.', category: 'infrastructure'),
            $this->check('incident_readiness', class_exists(IncidentService::class), 'Incident service is available.', 'Incident service is missing.', category: 'operations'),
        ];

        return $this->section($checks);
    }

    private function systemsReview(): array
    {
        $checks = collect([
            'installer' => Route::has('installer.index'),
            'auth' => Route::has('login') && Route::has('register'),
            'rbac' => Route::has('admin.index'),
            'localization' => Route::has('admin.localization'),
            'media' => class_exists(MediaService::class),
            'content' => class_exists(ContentService::class),
            'inbox' => Route::has('inbox.index'),
            'mail_pipeline' => class_exists(ProcessInboundMailIntake::class),
            'domain_pool' => class_exists(DomainPoolService::class),
            'provider_activation' => class_exists(ProviderActivationService::class),
            'billing' => Route::has('billing.webhooks.handle'),
            'api' => Route::has('api.v1.ping'),
            'operations' => Route::has('admin.operations'),
            'monitoring' => class_exists(MonitoringService::class),
            'automation' => class_exists(AutomationEngine::class),
        ])->map(fn (bool $passed, string $name): array => $this->check(
            "system_{$name}",
            $passed,
            ucfirst(str_replace('_', ' ', $name)).' foundation is available.',
            ucfirst(str_replace('_', ' ', $name)).' foundation is missing.',
            category: 'operations',
        ))->values()->all();

        return $this->section($checks);
    }

    private function section(array $checks): array
    {
        $blockers = collect($checks)->where('status', 'blocked')->values()->all();
        $warnings = collect($checks)->where('status', 'warning')->values()->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => [],
            'checks' => $checks,
        ];
    }

    private function check(
        string $name,
        bool $passed,
        string $passedMessage,
        string $failedMessage,
        string $failedStatus = 'blocked',
        string $category = 'operations',
    ): array {
        return [
            'name' => $name,
            'passed' => $passed,
            'status' => $passed ? 'passed' : $failedStatus,
            'message' => $passed ? $passedMessage : $failedMessage,
            'category' => $category,
        ];
    }

    private function summary(string $status, array $review): string
    {
        return match ($status) {
            'blocked' => 'RC3 certification is blocked by '.count($review['blockers']).' blocker(s).',
            'warning' => 'RC3 certification requires review of '.count($review['warnings']).' warning(s).',
            default => 'RC3 certification checks passed.',
        };
    }

    private function record(string $eventType, string $status): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info,
            OperationStatus::Detected,
            'rc3-certification',
            'RC3 certification event recorded.',
            ['status' => $status],
        );
    }
}
