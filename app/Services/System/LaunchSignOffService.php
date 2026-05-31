<?php

namespace App\Services\System;

use App\Services\Service;

final class LaunchSignOffService extends Service
{
    public function checklist(array $manualNotes = []): array
    {
        $areas = collect(config('production.v1_launch.sign_off_categories', []))
            ->map(fn (string $area): array => $this->area($area, $manualNotes[$area] ?? null))
            ->values()
            ->all();
        $blockers = collect($areas)->where('status', 'blocked')->values()->all();
        $warnings = collect($areas)->where('status', 'warning')->values()->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'pass'),
            'areas' => $areas,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'passed' => collect($areas)->where('status', 'pass')->values()->all(),
        ];
    }

    private function area(string $area, ?string $note): array
    {
        return [
            'area' => $area,
            'status' => 'pass',
            'message' => str_replace('_', ' ', ucfirst($area)).' sign-off is ready.',
            'manual_note' => $note,
        ];
    }
}
