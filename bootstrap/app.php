<?php

use App\Console\Commands\CleanupExpiredMailCommand;
use App\Console\Commands\DomainLiveReadinessCommand;
use App\Console\Commands\DomainOnboardingStatusCommand;
use App\Console\Commands\MailFirstLiveStatusCommand;
use App\Console\Commands\MailFirstRealCheckCommand;
use App\Console\Commands\MailProviderSandboxCheckCommand;
use App\Console\Commands\MonitoringHealthReviewCommand;
use App\Console\Commands\MonitoringIncidentReviewCommand;
use App\Console\Commands\OperationsCollectMetricsCommand;
use App\Console\Commands\OperationsHealthSummaryCommand;
use App\Console\Commands\ProviderActivationStatusCommand;
use App\Console\Commands\ProviderLiveReadinessCommand;
use App\Console\Commands\SystemAdminRoadmapStatusCommand;
use App\Console\Commands\SystemAnalyticsStatusCommand;
use App\Console\Commands\SystemApiRoadmapStatusCommand;
use App\Console\Commands\SystemAutomationRoadmapStatusCommand;
use App\Console\Commands\SystemDeploymentReadinessCommand;
use App\Console\Commands\SystemEcosystemStatusCommand;
use App\Console\Commands\SystemEnterpriseDataPolicyStatusCommand;
use App\Console\Commands\SystemEnterpriseDomainStatusCommand;
use App\Console\Commands\SystemEnterpriseStatusCommand;
use App\Console\Commands\SystemFirstLiveCheckCommand;
use App\Console\Commands\SystemGoLiveStatusCommand;
use App\Console\Commands\SystemGovernanceStatusCommand;
use App\Console\Commands\SystemGrowthStatusCommand;
use App\Console\Commands\SystemHealthCheckCommand;
use App\Console\Commands\SystemInboxRoadmapStatusCommand;
use App\Console\Commands\SystemLaunchMonitoringStatusCommand;
use App\Console\Commands\SystemLoadReadinessCommand;
use App\Console\Commands\SystemOrganizationRoadmapStatusCommand;
use App\Console\Commands\SystemProductIntelligenceCommand;
use App\Console\Commands\SystemPublicBetaStatusCommand;
use App\Console\Commands\SystemPublicLaunchStatusCommand;
use App\Console\Commands\SystemRC3CertificationCommand;
use App\Console\Commands\SystemReadinessCheckCommand;
use App\Console\Commands\SystemReleaseStatusCommand;
use App\Console\Commands\SystemRevenueStatusCommand;
use App\Console\Commands\SystemRoadmapStatusCommand;
use App\Console\Commands\SystemStagingReadinessCommand;
use App\Console\Commands\SystemSupportIntelligenceCommand;
use App\Console\Commands\SystemV11PlanStatusCommand;
use App\Console\Commands\SystemV1LaunchStatusCommand;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureApplicationInstalled;
use App\Http\Middleware\EnsureInstallerAccessible;
use App\Http\Middleware\EnsureStaffHasPermission;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CleanupExpiredMailCommand::class,
        DomainLiveReadinessCommand::class,
        DomainOnboardingStatusCommand::class,
        MailFirstLiveStatusCommand::class,
        MailFirstRealCheckCommand::class,
        MailProviderSandboxCheckCommand::class,
        MonitoringHealthReviewCommand::class,
        MonitoringIncidentReviewCommand::class,
        OperationsCollectMetricsCommand::class,
        OperationsHealthSummaryCommand::class,
        ProviderActivationStatusCommand::class,
        ProviderLiveReadinessCommand::class,
        SystemAdminRoadmapStatusCommand::class,
        SystemAnalyticsStatusCommand::class,
        SystemApiRoadmapStatusCommand::class,
        SystemAutomationRoadmapStatusCommand::class,
        SystemDeploymentReadinessCommand::class,
        SystemEnterpriseDataPolicyStatusCommand::class,
        SystemEnterpriseDomainStatusCommand::class,
        SystemEnterpriseStatusCommand::class,
        SystemEcosystemStatusCommand::class,
        SystemFirstLiveCheckCommand::class,
        SystemGoLiveStatusCommand::class,
        SystemGovernanceStatusCommand::class,
        SystemGrowthStatusCommand::class,
        SystemHealthCheckCommand::class,
        SystemInboxRoadmapStatusCommand::class,
        SystemLaunchMonitoringStatusCommand::class,
        SystemLoadReadinessCommand::class,
        SystemOrganizationRoadmapStatusCommand::class,
        SystemPublicBetaStatusCommand::class,
        SystemPublicLaunchStatusCommand::class,
        SystemProductIntelligenceCommand::class,
        SystemReadinessCheckCommand::class,
        SystemRC3CertificationCommand::class,
        SystemReleaseStatusCommand::class,
        SystemRevenueStatusCommand::class,
        SystemRoadmapStatusCommand::class,
        SystemSupportIntelligenceCommand::class,
        SystemV11PlanStatusCommand::class,
        SystemStagingReadinessCommand::class,
        SystemV1LaunchStatusCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->priority([
            EnsureApplicationInstalled::class,
        ]);

        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
            'app.installed' => EnsureApplicationInstalled::class,
            'installer.accessible' => EnsureInstallerAccessible::class,
            'staff.active' => EnsureStaffIsActive::class,
            'staff.permission' => EnsureStaffHasPermission::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'billing/webhooks/*',
            'webhooks/mailgun',
            'webhooks/postmark',
            'webhooks/ses',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
