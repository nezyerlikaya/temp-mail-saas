<?php

namespace App\Services\System;

use App\Services\Operations\UptimeReadinessService;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class LaunchChecklistService extends Service
{
    public function __construct(
        private readonly ProductionReadinessChecklistService $production,
        private readonly BackupReadinessService $backup,
        private readonly UptimeReadinessService $uptime,
    ) {}

    public function report(): array
    {
        $checks = [
            ...$this->infrastructureChecks(),
            ...$this->securityChecks(),
            ...$this->monitoringChecks(),
            ...$this->backupChecks(),
            ...$this->providerChecks(),
            ...$this->domainChecks(),
            ...$this->billingChecks(),
            ...$this->operationsChecks(),
        ];

        return [
            'target' => (string) config('production.launch.target', 'v1'),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'recommendations' => collect($checks)->where('classification', 'recommendation')->values()->all(),
            'informational' => collect($checks)->where('classification', 'informational')->values()->all(),
            'checks' => $checks,
        ];
    }

    public function deploymentChecklist(): array
    {
        return [
            'shared_hosting' => (bool) config('production.deployment.checklists.shared_hosting', true),
            'vps' => (bool) config('production.deployment.checklists.vps', true),
            'queue_workers' => (bool) config('production.deployment.checklists.queue_workers', true),
            'scheduler' => (bool) config('production.deployment.checklists.scheduler', true),
        ];
    }

    private function infrastructureChecks(): array
    {
        $production = $this->production->report();
        $deployment = $this->deploymentChecklist();

        return [
            $this->check('production_readiness_blockers', count($production['blockers']) === 0, 'Production readiness has no blockers.', 'Production readiness has blockers.', 'blocker', ['count' => count($production['blockers'])], 'infrastructure'),
            $this->check('deployment_checklists_available', collect($deployment)->every(fn (bool $available): bool => $available), 'Deployment checklists are available.', 'One or more deployment checklists are missing.', 'warning', $deployment, 'infrastructure'),
        ];
    }

    private function securityChecks(): array
    {
        return [
            $this->check('debug_disabled_for_launch', ! (bool) config('app.debug', false), 'Debug mode is disabled.', 'Debug mode is enabled.', 'blocker', [], 'security'),
            $this->check('app_key_ready_for_launch', filled((string) config('app.key')), 'APP_KEY is configured.', 'APP_KEY is missing.', 'blocker', [], 'security'),
        ];
    }

    private function monitoringChecks(): array
    {
        $uptime = $this->uptime->report();
        $required = (bool) config('production.launch.require_monitoring_ready', true);

        return [
            $this->check('uptime_readiness', $uptime['status'] === 'ready' || ! $required, 'Uptime readiness checks passed.', 'Uptime readiness needs attention.', $required ? 'blocker' : 'warning', ['status' => $uptime['status']], 'monitoring'),
            $this->check('monitoring_commands_registered', class_exists(\App\Console\Commands\MonitoringHealthReviewCommand::class), 'Monitoring review commands are registered.', 'Monitoring review commands are missing.', 'warning', [], 'monitoring'),
        ];
    }

    private function backupChecks(): array
    {
        $backup = $this->backup->report();
        $required = (bool) config('production.launch.require_backup_ready', true);

        return [
            $this->check('backup_ready_for_launch', $backup['ready'] === true || ! $required, 'Backup readiness checks passed.', 'Backup readiness needs attention.', $required ? 'blocker' : 'warning', ['checks' => count($backup['checks'])], 'backups'),
        ];
    }

    private function providerChecks(): array
    {
        $providers = config('production.onboarding.providers', []);
        $providers = is_array($providers) ? $providers : [];
        $docsRequired = (bool) config('production.launch.require_provider_onboarding_docs', true);

        return [
            $this->check('mailgun_webhook_route_ready', Route::has('webhooks.mailgun'), 'Mailgun webhook route is registered.', 'Mailgun webhook route is missing.', 'warning', [], 'providers'),
            $this->check('postmark_webhook_route_ready', Route::has('webhooks.postmark'), 'Postmark webhook route is registered.', 'Postmark webhook route is missing.', 'warning', [], 'providers'),
            $this->check('ses_webhook_route_ready', Route::has('webhooks.ses'), 'Amazon SES webhook route is registered.', 'Amazon SES webhook route is missing.', 'warning', [], 'providers'),
            $this->check('provider_onboarding_docs', ! $docsRequired || is_file(base_path('docs/deployment/provider-onboarding.md')), 'Provider onboarding guidance is documented.', 'Provider onboarding guidance is missing.', 'warning', ['providers' => $providers], 'providers'),
        ];
    }

    private function domainChecks(): array
    {
        $docsRequired = (bool) config('production.launch.require_domain_onboarding_docs', true);

        return [
            $this->check('domain_pool_configured', is_array(config('domains-pool.fallback_domains', [])), 'Domain pool configuration is available.', 'Domain pool configuration is missing.', 'warning', [], 'domains'),
            $this->check('domain_onboarding_docs', ! $docsRequired || is_file(base_path('docs/deployment/domain-onboarding.md')), 'Domain onboarding guidance is documented.', 'Domain onboarding guidance is missing.', 'warning', [], 'domains'),
        ];
    }

    private function billingChecks(): array
    {
        return [
            $this->check('billing_webhook_route_ready', Route::has('billing.webhooks.handle'), 'Billing webhook route is registered.', 'Billing webhook route is missing.', 'warning', [], 'billing'),
            $this->check('billing_secrets_not_required_for_local', filled((string) config('billing.default_provider', 'local')), 'Billing provider configuration is available.', 'Billing provider configuration is missing.', 'warning', [], 'billing'),
        ];
    }

    private function operationsChecks(): array
    {
        return [
            $this->check('admin_operations_ready', Route::has('admin.operations'), 'Admin operations route is registered.', 'Admin operations route is missing.', 'warning', [], 'operations'),
            $this->check('go_live_command_ready', class_exists(\App\Console\Commands\SystemGoLiveStatusCommand::class), 'Go-live status command is registered.', 'Go-live status command is missing.', 'warning', [], 'operations'),
        ];
    }

    private function check(
        string $name,
        bool $passed,
        string $passedMessage,
        string $failedMessage,
        string $classification,
        array $metadata = [],
        string $category = 'operations',
    ): array {
        return [
            'category' => $category,
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'informational' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
            'metadata' => $metadata,
        ];
    }
}
