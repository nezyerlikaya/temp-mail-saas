<?php

namespace Tests\Feature;

use App\Services\System\GoLiveStatusService;
use App\Services\System\LaunchChecklistService;
use App\Services\System\RollbackReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchGoLiveReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_config_contains_launch_and_rollback_options(): void
    {
        $this->assertIsArray(config('production.launch'));
        $this->assertIsArray(config('production.rollback'));
        $this->assertIsArray(config('production.deployment.checklists'));
        $this->assertSame('v1', config('production.launch.target'));
    }

    public function test_launch_checklist_service_reports_all_categories(): void
    {
        $this->configureReadyLaunch();

        $report = app(LaunchChecklistService::class)->report();
        $categories = collect($report['checks'])->pluck('category')->unique()->values()->all();

        $this->assertContains('infrastructure', $categories);
        $this->assertContains('security', $categories);
        $this->assertContains('monitoring', $categories);
        $this->assertContains('backups', $categories);
        $this->assertContains('providers', $categories);
        $this->assertContains('domains', $categories);
        $this->assertContains('billing', $categories);
        $this->assertContains('operations', $categories);
        $this->assertArrayHasKey('blockers', $report);
        $this->assertArrayHasKey('warnings', $report);
        $this->assertArrayHasKey('recommendations', $report);
    }

    public function test_go_live_status_ready_warning_and_blocked_classifications_work(): void
    {
        $this->configureReadyLaunch();

        $ready = app(GoLiveStatusService::class)->evaluate();
        $this->assertSame('ready', $ready['state']);

        config(['production.deployment.checklists.scheduler' => false]);
        $warning = app(GoLiveStatusService::class)->evaluate();
        $this->assertSame('warning', $warning['state']);
        $this->assertContains('deployment_checklists_available', array_column($warning['warnings'], 'name'));

        config(['app.debug' => true]);
        $blocked = app(GoLiveStatusService::class)->evaluate();
        $this->assertSame('blocked', $blocked['state']);
        $this->assertContains('debug_disabled_for_launch', array_column($blocked['blockers'], 'name'));
    }

    public function test_rollback_readiness_service_reports_prerequisites_and_risks(): void
    {
        $this->configureReadyLaunch();

        $ready = app(RollbackReadinessService::class)->report();
        $this->assertTrue($ready['ready']);
        $this->assertContains('rollback_backup_ready', array_column($ready['checks'], 'name'));
        $this->assertContains('rollback_deployment_notes', array_column($ready['checks'], 'name'));
        $this->assertContains('rollback_restore_prerequisites', array_column($ready['checks'], 'name'));

        config(['production.backup.restore_prerequisites_documented' => false]);

        $risk = app(RollbackReadinessService::class)->report();
        $this->assertFalse($risk['ready']);
        $this->assertContains('rollback_backup_ready', array_column($risk['risks'], 'name'));
    }

    public function test_backup_verification_integrates_with_launch_blockers(): void
    {
        $this->configureReadyLaunch();
        config(['production.backup.restore_prerequisites_documented' => false]);

        $status = app(GoLiveStatusService::class)->evaluate();

        $this->assertSame('blocked', $status['state']);
        $this->assertContains('backup_ready_for_launch', array_column($status['blockers'], 'name'));
    }

    public function test_deployment_checklist_evaluation_can_warn_without_blocking(): void
    {
        $this->configureReadyLaunch();
        config([
            'production.deployment.checklists.shared_hosting' => true,
            'production.deployment.checklists.vps' => false,
        ]);

        $report = app(LaunchChecklistService::class)->report();

        $this->assertContains('deployment_checklists_available', array_column($report['warnings'], 'name'));
        $this->assertSame([], $report['blockers']);
    }

    public function test_go_live_status_command_outputs_safe_summary(): void
    {
        $this->configureReadyLaunch();

        $this->artisan('system:go-live-status')
            ->expectsOutput('Go-live status: READY')
            ->expectsOutput('Target: v1')
            ->expectsOutputToContain('Blockers:')
            ->expectsOutputToContain('Warnings:')
            ->expectsOutputToContain('Recommendations:')
            ->assertSuccessful();
    }

    public function test_go_live_status_command_fails_when_blocked(): void
    {
        $this->configureReadyLaunch();
        config(['app.key' => null]);

        $this->artisan('system:go-live-status')
            ->expectsOutput('Go-live status: BLOCKED')
            ->expectsOutputToContain('Blocker: app_key_ready_for_launch')
            ->assertFailed();
    }

    private function configureReadyLaunch(): void
    {
        config([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('g', 32)),
            'app.url' => 'https://example.test',
            'queue.default' => 'database',
            'mail.default' => 'array',
            'production.monitoring.operations_metrics_required' => false,
            'production.backup.restore_prerequisites_documented' => true,
            'production.backup.retention_guidance_documented' => true,
            'production.deployment.checklists.shared_hosting' => true,
            'production.deployment.checklists.vps' => true,
            'production.deployment.checklists.queue_workers' => true,
            'production.deployment.checklists.scheduler' => true,
            'production.launch.require_monitoring_ready' => true,
            'production.launch.require_backup_ready' => true,
            'production.launch.require_provider_onboarding_docs' => true,
            'production.launch.require_domain_onboarding_docs' => true,
        ]);
    }
}
