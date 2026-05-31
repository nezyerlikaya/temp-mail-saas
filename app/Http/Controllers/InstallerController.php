<?php

namespace App\Http\Controllers;

use App\Services\System\EnvironmentWriterService;
use App\Services\System\InstallationService;
use App\Services\System\InstallerDatabaseService;
use App\Services\System\InstallerLockService;
use App\Services\System\InstallerRequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class InstallerController extends Controller
{
    public function index(InstallationService $installation): View
    {
        return view('install.welcome', [
            'status' => $installation->status(),
        ]);
    }

    public function requirements(InstallerRequirementService $requirements): View
    {
        return view('install.requirements', [
            'requirements' => $requirements->results(),
        ]);
    }

    public function environment(InstallationService $installation): View
    {
        return view('install.environment', [
            'status' => $installation->status(),
        ]);
    }

    public function database(InstallerDatabaseService $database): View
    {
        return view('install.database', [
            'database' => $database->status(),
        ]);
    }

    public function finish(InstallationService $installation): View
    {
        return view('install.finish', [
            'status' => $installation->status(),
        ]);
    }

    public function complete(
        EnvironmentWriterService $environment,
        InstallationService $installation,
        InstallerLockService $lock,
    ): RedirectResponse {
        if (! $installation->environmentStatus()['app_key_in_env']) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $environment->write(['APP_KEY' => $key]);
            config(['app.key' => $key]);
        }

        $lock->create();

        return redirect()->route('admin.login');
    }
}
