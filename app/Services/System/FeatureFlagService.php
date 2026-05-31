<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Arr;

final class FeatureFlagService extends Service
{
    public function enabled(string $key): bool
    {
        return Arr::get(config('features', []), $key, false) === true;
    }

    public function disabled(string $key): bool
    {
        return ! $this->enabled($key);
    }
}
