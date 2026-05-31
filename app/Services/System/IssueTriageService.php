<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Str;

final class IssueTriageService extends Service
{
    public function classify(string $severity, string $owner, ?string $title = null): array
    {
        $severity = $this->severity($severity);
        $owner = $this->owner($owner);

        return [
            'severity' => $severity,
            'owner' => $owner,
            'priority' => $this->priority($severity),
            'title' => $title !== null ? Str::limit($title, 120, '') : null,
            'response' => $this->response($severity),
        ];
    }

    public function severity(string $severity): string
    {
        return in_array($severity, ['critical', 'high', 'medium', 'low'], true) ? $severity : 'medium';
    }

    public function owner(string $owner): string
    {
        return in_array($owner, ['platform', 'provider', 'domain', 'billing', 'operations', 'support'], true)
            ? $owner
            : 'support';
    }

    private function priority(string $severity): int
    {
        return match ($severity) {
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            default => 4,
        };
    }

    private function response(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Immediate operator review before beta continues.',
            'high' => 'Same-day owner review.',
            'medium' => 'Triage during the next beta review window.',
            default => 'Track for planned beta iteration.',
        };
    }
}
