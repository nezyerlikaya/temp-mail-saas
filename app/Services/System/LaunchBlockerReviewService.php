<?php

namespace App\Services\System;

use App\Services\Service;

final class LaunchBlockerReviewService extends Service
{
    public function review(array $sections): array
    {
        $blockers = collect($sections)
            ->flatMap(fn (array $section, string $sectionName): array => $this->items($section, 'blockers', $sectionName, 'blocker'))
            ->values()
            ->all();
        $warnings = collect($sections)
            ->flatMap(fn (array $section, string $sectionName): array => $this->items($section, 'warnings', $sectionName, 'warning'))
            ->values()
            ->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified'),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $this->recommendations($blockers, $warnings),
            'counts' => [
                'blockers' => count($blockers),
                'warnings' => count($warnings),
            ],
        ];
    }

    private function items(array $section, string $key, string $sectionName, string $severity): array
    {
        return collect($section[$key] ?? [])
            ->map(function (array $item) use ($sectionName, $severity): array {
                $category = $this->category((string) ($item['category'] ?? $sectionName));

                return [
                    'section' => $sectionName,
                    'category' => $category,
                    'severity' => $severity,
                    'owner' => $this->owner($category),
                    'name' => (string) ($item['name'] ?? 'readiness_review'),
                    'message' => (string) ($item['message'] ?? 'Readiness review needs attention.'),
                ];
            })
            ->all();
    }

    private function recommendations(array $blockers, array $warnings): array
    {
        return collect([...$blockers, ...$warnings])
            ->map(fn (array $item): string => "Review {$item['category']} ownership with {$item['owner']}: {$item['message']}")
            ->push('Complete the RC3 sign-off checklist before production deployment.')
            ->unique()
            ->values()
            ->all();
    }

    private function category(string $category): string
    {
        return match ($category) {
            'backups', 'installer', 'staging', 'go_live', 'server' => 'infrastructure',
            'providers', 'first_real_mail' => 'provider',
            'domains' => 'domain',
            'monitoring' => 'operations',
            default => array_key_exists($category, (array) config('production.rc3.launch_certification', []))
                ? $category
                : 'operations',
        };
    }

    private function owner(string $category): string
    {
        return match ($category) {
            'security' => 'security',
            'infrastructure', 'queue' => 'platform',
            'provider', 'domain' => 'mail-operations',
            'billing' => 'billing-operations',
            default => 'operations',
        };
    }
}
