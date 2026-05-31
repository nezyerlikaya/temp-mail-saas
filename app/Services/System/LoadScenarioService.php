<?php

namespace App\Services\System;

use App\Services\Service;

final class LoadScenarioService extends Service
{
    public function scenarios(): array
    {
        return collect(config('load-testing.scenarios', []))
            ->map(fn (array $scenario, string $key): array => [
                'key' => $key,
                'label' => (string) ($scenario['label'] ?? $key),
                'assumption' => (int) ($scenario['assumption'] ?? 0),
                'unit' => (string) ($scenario['unit'] ?? 'unit'),
                'generates_traffic' => false,
            ])
            ->values()
            ->all();
    }

    public function summary(): array
    {
        $scenarios = $this->scenarios();

        return [
            'status' => $scenarios === [] ? 'warning' : 'ready',
            'scenario_count' => count($scenarios),
            'scenarios' => $scenarios,
            'recommendations' => [
                'Run these scenarios as operator checklists before any external load test.',
                'Keep shared-hosting limits and queue worker capacity visible during validation.',
                'Do not generate real high-volume traffic from this application.',
            ],
        ];
    }
}
