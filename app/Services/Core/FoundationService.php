<?php

namespace App\Services\Core;

use App\Services\Service;

final class FoundationService extends Service
{
    public function status(): array
    {
        return [
            'application' => config('tempmail.name'),
            'architecture' => config('tempmail.architecture.type'),
            'ready' => true,
        ];
    }
}
