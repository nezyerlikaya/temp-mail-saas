<?php

namespace App\Services\Roadmap;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Str;

final class AutomationEnhancementPrioritizationService extends Service
{
    public function __construct(
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(array $reviews = []): array
    {
        $candidates = collect(config('automation-roadmap.roadmap.candidates', []))
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
        $report = [
            'candidate_count' => $candidates->count(),
            'quick_wins' => $candidates
                ->whereIn('complexity', ['small', 'medium'])
                ->where('risk', 'low')
                ->take((int) config('automation-roadmap.prioritization.quick_win_limit', 3))
                ->values()
                ->all(),
            'high_impact_enhancements' => $candidates
                ->whereIn('impact', ['high', 'critical'])
                ->take((int) config('automation-roadmap.prioritization.high_impact_limit', 4))
                ->values()
                ->all(),
            'low_risk_improvements' => $candidates
                ->where('risk', 'low')
                ->take((int) config('automation-roadmap.prioritization.low_risk_limit', 4))
                ->values()
                ->all(),
            'deferred_candidates' => $candidates
                ->filter(fn (array $candidate): bool => $candidate['risk'] !== 'low' || $candidate['complexity'] === 'large')
                ->values()
                ->all(),
            'review_signals' => [
                'automation_state' => $reviews['automation']['state'] ?? 'unknown',
                'intelligence_state' => $reviews['intelligence']['state'] ?? 'unknown',
                'lifecycle_state' => $reviews['lifecycle']['state'] ?? 'unknown',
                'operations_state' => $reviews['operations']['state'] ?? 'unknown',
            ],
        ];

        $this->operations->log(
            OperationCategory::System,
            'automation_priorities_generated',
            OperationSeverity::Info,
            OperationStatus::Detected,
            'automation-roadmap',
            'Automation enhancement priorities generated.',
            [
                'candidate_count' => $report['candidate_count'],
                'quick_win_count' => count($report['quick_wins']),
                'deferred_count' => count($report['deferred_candidates']),
            ],
        );

        return $report;
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
