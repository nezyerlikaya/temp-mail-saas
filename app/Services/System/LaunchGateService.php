<?php

namespace App\Services\System;

use App\Services\Service;

final class LaunchGateService extends Service
{
    public function evaluate(array $sections): array
    {
        $categories = ['security', 'providers', 'domains', 'queue', 'billing', 'api', 'operations', 'support'];
        $gates = collect($categories)
            ->mapWithKeys(fn (string $category): array => [$category => $this->gate($category, $sections[$category] ?? [])])
            ->all();
        $blockers = collect($gates)->flatMap(fn (array $gate): array => $gate['blockers'])->values()->all();
        $warnings = collect($gates)->flatMap(fn (array $gate): array => $gate['warnings'])->values()->all();

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'gates' => $gates,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([...$blockers, ...$warnings])->pluck('message')->unique()->values()->all(),
        ];
    }

    private function gate(string $category, array $section): array
    {
        $enabled = (bool) config("production.public_launch.gates.{$category}", true);
        $blockers = $enabled ? $this->items($section, ['blockers', 'critical']) : [];
        $warnings = $enabled ? $this->items($section, ['warnings']) : [];

        return [
            'category' => $category,
            'enabled' => $enabled,
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'blockers' => collect($blockers)->map(fn (array $item): array => ['category' => $category, ...$item])->all(),
            'warnings' => collect($warnings)->map(fn (array $item): array => ['category' => $category, ...$item])->all(),
        ];
    }

    private function items(array $section, array $keys): array
    {
        return collect($keys)
            ->flatMap(fn (string $key): array => is_array($section[$key] ?? null) ? $section[$key] : [])
            ->values()
            ->all();
    }
}
