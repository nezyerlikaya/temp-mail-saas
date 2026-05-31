<?php

namespace Tests\Feature;

use App\Services\System\EnvironmentWriterService;
use App\Services\System\InstallationService;
use App\Services\System\InstallerLockService;
use App\Services\System\InstallerRequirementService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallerFoundationTest extends TestCase
{
    private string $installerTestPath;

    private string $envPath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerTestPath = storage_path('framework/testing/installer');
        $this->envPath = $this->installerTestPath.'/.env';
        $this->lockPath = $this->installerTestPath.'/install.lock';

        File::deleteDirectory($this->installerTestPath);
        File::ensureDirectoryExists($this->installerTestPath);

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'installer.env_path' => $this->envPath,
            'installer.lock_path' => $this->lockPath,
        ]);

        File::put($this->envPath, "APP_KEY=configured\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installerTestPath);

        parent::tearDown();
    }

    public function test_installer_routes_are_accessible_when_not_installed(): void
    {
        File::delete($this->envPath);

        $this->get('/install')
            ->assertOk()
            ->assertSee('Installation');

        $this->get('/install/requirements')->assertOk()->assertSee('Requirements');
        $this->get('/install/environment')->assertOk()->assertSee('Environment');
        $this->get('/install/database')->assertOk()->assertSee('Database');
        $this->get('/install/finish')->assertOk()->assertSee('Finish');
    }

    public function test_installer_is_blocked_when_installed_and_healthy(): void
    {
        app(InstallerLockService::class)->create();

        $this->get('/install')
            ->assertRedirect(route('home'));
    }

    public function test_recovery_mode_activates_when_env_file_is_missing(): void
    {
        app(InstallerLockService::class)->create();
        File::delete($this->envPath);

        $status = app(InstallationService::class)->status();

        $this->assertTrue($status['recovery']);
        $this->assertTrue($status['installer_accessible']);
        $this->get('/install')->assertOk();
    }

    public function test_app_key_missing_triggers_installer_recovery(): void
    {
        app(InstallerLockService::class)->create();
        File::put($this->envPath, "APP_NAME=Temp Mail SaaS\n");

        $status = app(InstallationService::class)->status();

        $this->assertTrue($status['recovery']);
        $this->assertTrue($status['installer_accessible']);
        $this->get('/install')->assertOk();
    }

    public function test_requirement_checks_return_structured_results(): void
    {
        $results = app(InstallerRequirementService::class)->results();

        $this->assertArrayHasKey('ok', $results);
        $this->assertArrayHasKey('checks', $results);
        $this->assertNotEmpty($results['checks']);
        $this->assertContains('php', array_column($results['checks'], 'key'));
        $this->assertContains('database_driver', array_column($results['checks'], 'key'));
    }

    public function test_environment_writer_updates_values_safely(): void
    {
        $path = $this->installerTestPath.'/custom.env';
        File::put($path, "APP_NAME=Old\nDB_PASSWORD=secret\n");

        $result = (new EnvironmentWriterService($path))->write([
            'APP_NAME' => 'Temp Mail SaaS',
            'APP_KEY' => 'base64:hidden',
        ]);

        $contents = File::get($path);

        $this->assertTrue($result['ok']);
        $this->assertSame(['APP_NAME', 'APP_KEY'], $result['written']);
        $this->assertStringContainsString('APP_NAME="Temp Mail SaaS"', $contents);
        $this->assertStringContainsString('DB_PASSWORD=secret', $contents);
        $this->assertStringContainsString('APP_KEY=base64:hidden', $contents);
        $this->assertStringNotContainsString('hidden', json_encode($result));
    }

    public function test_installer_lock_can_be_created_and_removed(): void
    {
        $lock = app(InstallerLockService::class);

        $this->assertFalse($lock->locked());
        $this->assertTrue($lock->create()['ok']);
        $this->assertTrue($lock->locked());
        $this->assertTrue($lock->remove()['ok']);
        $this->assertFalse($lock->locked());
    }

    public function test_finish_creates_lock_and_redirects_to_admin_login(): void
    {
        File::delete($this->envPath);

        $this->post('/install/finish')
            ->assertRedirect('/admin/login');

        $this->assertFileExists($this->lockPath);
        $this->assertStringContainsString('APP_KEY=', File::get($this->envPath));
    }

    public function test_existing_public_and_auth_routes_continue_working(): void
    {
        app(InstallerLockService::class)->create();

        $this->get('/')->assertOk();
        $this->getJson('/health')->assertOk();
        $this->get('/status')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }
}
