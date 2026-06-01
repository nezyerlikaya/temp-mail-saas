<?php

namespace App\Services\Governance;

use App\Services\Service;

final class PlatformRiskService extends Service
{
    private const LEVELS = ['low', 'medium', 'high', 'critical'];

    public function review(): array
    {
        $risks = [
            $this->risk('operational_risk', config('governance.risk.operational_risk', 'low'), 'Operational risk review.'),
            $this->risk('dependency_risk', config('governance.risk.dependency_risk', 'low'), 'Dependency risk review.'),
            $this->risk('governance_risk', config('governance.risk.governance_risk', 'low'), 'Governance risk review.'),
            $this->risk('sustainability_risk', config('governance.risk.sustainability_risk', 'low'), 'Sustainability risk review.'),
        ];
        $critical = collect($risks)->where('level', 'critical')->values()->all();
        $high = collect($risks)->where('level', 'high')->values()->all();
        $status = (bool) config('governance.risk.block_on_critical', true) && $critical !== []
            ? 'blocked'
            : (((bool) config('governance.risk.warn_on_high', true) && $high !== []) || collect($risks)->contains(fn (array $risk): bool => $risk['level'] === 'medium') ? 'warning' : 'ready');

        return [
            'status' => $status,
            'risks' => $risks,
            'critical' => $critical,
            'high' => $high,
            'recommendations' => collect([...$critical, ...$high])->pluck('message')->values()->all(),
        ];
    }

    private function risk(string $name, mixed $level, string $message): array
    {
        $level = in_array((string) $level, self::LEVELS, true) ? (string) $level : 'low';

        return compact('name', 'level', 'message');
    }
}
