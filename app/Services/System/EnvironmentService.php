<?php

namespace App\Services\System;

use App\Services\Service;

final class EnvironmentService extends Service
{
    public function summary(): array
    {
        return [
            'environment' => $this->environment(),
            'app_key' => $this->appKey(),
            'debug' => $this->debug(),
            'cache' => $this->cache(),
            'storage' => $this->storage(),
        ];
    }

    public function environment(): array
    {
        return [
            'name' => app()->environment(),
            'production' => app()->environment('production'),
        ];
    }

    public function appKey(): array
    {
        return [
            'configured' => filled((string) config('app.key')),
        ];
    }

    public function debug(): array
    {
        return [
            'enabled' => (bool) config('app.debug', false),
        ];
    }

    public function cache(): array
    {
        return [
            'default_store' => (string) config('cache.default', 'file'),
            'prefix_configured' => filled((string) config('cache.prefix')),
        ];
    }

    public function storage(): array
    {
        return [
            'path' => 'storage',
            'available' => is_dir(storage_path()),
            'writable' => is_writable(storage_path()),
        ];
    }
}
