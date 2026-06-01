<?php

namespace App\Services\Roadmap;

use App\Services\Service;

final class ReleasePrioritizationService extends Service
{
    public function summarize(): array
    {
        $v11 = $this->candidates('v1.1');
        $v12 = $this->candidates('v1.2');
        $future = $this->candidates('future');

        return [
            'v1.1' => $v11,
            'v1.2' => $v12,
            'future' => $future,
            'counts' => [
                'v1.1' => count($v11),
                'v1.2' => count($v12),
                'future' => count($future),
            ],
        ];
    }

    private function candidates(string $release): array
    {
        return collect(config('roadmap.prioritization', [])[$release] ?? [])
            ->map(fn (array $candidate): array => [
                'category' => (string) ($candidate['category'] ?? 'platform'),
                'candidate' => (string) ($candidate['candidate'] ?? 'Review candidate'),
                'priority' => (string) ($candidate['priority'] ?? 'low'),
            ])
            ->values()
            ->all();
    }
}
