<?php

namespace App\Support\Traits;

use App\Enums\AppFeature;

trait ResolvesFeatureFlags
{
    protected function featureEnabled(AppFeature $feature): bool
    {
        return $feature->enabled();
    }
}
