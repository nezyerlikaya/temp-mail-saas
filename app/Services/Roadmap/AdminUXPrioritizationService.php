<?php

namespace App\Services\Roadmap;

use App\Services\Service;
use Illuminate\Support\Str;

final class AdminUXPrioritizationService extends Service
{
    public function report(array $reviews = []): array
    {
        $candidates = collect(config('admin-roadmap.roadmap.candidates', []))
            ->map(fn (array $candidate): array => [
                'key' => (string) $candidate['key'],
                'title' => $this->safeText((string) $candidate['title']),
                'category' => (string) $candidate['category'],
                'priority' => (string) $candidate['priority'],
                'impact' => (string) $candidate['impact'],
                'complexity' => (string) $candidate['complexity'],
                'risk' => (string) $candidate['risk'],
                'score' => $this->score($candidate),
            ])
            ->sortByDesc('score')
            ->values();

        return [
            'candidate_count' => $candidates->count(),
            'quick_wins' => $candidates
                ->whereIn('complexity', ['small', 'medium'])
                ->where('risk', 'low')
                ->take((int) config('admin-roadmap.ux.quick_win_limit', 3))
                ->values()
                ->all(),
            'operational_bottlenecks' => $candidates
                ->whereIn('category', ['operations-workflow', 'dashboard'])
                ->take((int) config('admin-roadmap.ux.bottleneck_limit', 4))
                ->values()
                ->all(),
            'high_impact_improvements' => $candidates
                ->whereIn('impact', ['high', 'critical'])
                ->take((int) config('admin-roadmap.ux.high_impact_limit', 4))
                ->values()
                ->all(),
            'deferred_improvements' => $candidates
                ->filter(fn (array $candidate): bool => $candidate['risk'] !== 'low' || $candidate['complexity'] === 'large')
                ->values()
                ->all(),
            'review_signals' => [
                'admin_state' => $reviews['admin']['state'] ?? 'unknown',
                'operations_state' => $reviews['operations']['state'] ?? 'unknown',
                'dashboard_state' => $reviews['dashboard']['state'] ?? 'unknown',
                'accessibility_state' => $reviews['accessibility']['state'] ?? 'unknown',
            ],
        ];
    }

    private function score(array $candidate): int
    {
        return $this->weight((string) ($candidate['priority'] ?? 'medium'))
            + $this->weight((string) ($candidate['impact'] ?? 'medium'))
            - $this->complexity((string) ($candidate['complexity'] ?? 'medium'))
            - $this->risk((string) ($candidate['risk'] ?? 'medium'));
    }

    private function weight(string $value): int
    {
        return match ($value) {
            'critical' => 8,
            'high' => 6,
            'medium' => 4,
            default => 2,
        };
    }

    private function complexity(string $value): int
    {
        return match ($value) {
            'epic' => 8,
            'large' => 6,
            'medium' => 3,
            default => 1,
        };
    }

    private function risk(string $value): int
    {
        return match ($value) {
            'critical' => 8,
            'high' => 6,
            'medium' => 3,
            default => 1,
        };
    }

    private function safeText(string $value): string
    {
        return Str::of(strip_tags($value))
            ->replaceMatches('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]')
            ->limit(120, '')
            ->toString();
    }
}
