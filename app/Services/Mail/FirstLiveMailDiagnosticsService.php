<?php

namespace App\Services\Mail;

use App\Services\Service;

final class FirstLiveMailDiagnosticsService extends Service
{
    public function analyze(array $sections, array $trace): array
    {
        $blockers = collect($sections)
            ->flatMap(fn (array $section): array => $section['blockers'] ?? [])
            ->merge($trace['blockers'])
            ->values()
            ->all();
        $warnings = collect($sections)
            ->flatMap(fn (array $section): array => $section['warnings'] ?? [])
            ->merge($trace['warnings'])
            ->values()
            ->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'traceability' => $trace['status'],
            'observability' => (bool) config('mail-providers.first_live_mail.diagnostics.observability_ready', true) ? 'ready' : 'warning',
            'recommendations' => collect([...$blockers, ...$warnings])
                ->pluck('message')
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
