<?php

namespace App\Services\Abuse;

use App\Enums\AbuseStatus;
use App\Services\Service;

final class AbuseDecisionService extends Service
{
    public function decide(array $signals = []): array
    {
        $riskScore = max(0, min(100, (int) ($signals['risk_score'] ?? $this->riskScore($signals))));
        $thresholds = config('abuse.risk_score_thresholds', []);
        $status = match (true) {
            $riskScore >= (int) ($thresholds['escalate'] ?? 90) => AbuseStatus::Escalated,
            $riskScore >= (int) ($thresholds['block'] ?? 70) => AbuseStatus::Blocked,
            $riskScore >= (int) ($thresholds['throttle'] ?? 40) => AbuseStatus::Throttled,
            default => AbuseStatus::Observed,
        };
        $restricted = $status !== AbuseStatus::Observed;

        return [
            'allowed' => ! in_array($status, [AbuseStatus::Blocked, AbuseStatus::Escalated], true),
            'status' => $status->value,
            'risk_score' => $riskScore,
            'cooldown_seconds' => $restricted ? $this->cooldown($riskScore) : 0,
            'requires_captcha' => $status === AbuseStatus::Escalated
                && (bool) config('abuse.captcha_escalation_enabled', false),
            'reason' => $restricted ? 'Request frequency requires a cooldown.' : null,
        ];
    }

    private function riskScore(array $signals): int
    {
        return ((int) ($signals['recent_attempts'] ?? 0) * 5)
            + ((int) ($signals['throttle_count'] ?? 0) * 20)
            + ((bool) ($signals['honeypot_triggered'] ?? false) ? 80 : 0);
    }

    private function cooldown(int $riskScore): int
    {
        $base = max(1, (int) config('abuse.cooldown_seconds', 60));

        if (! config('abuse.progressive_penalties.enabled', true)) {
            return $base;
        }

        $multiplier = max(1, (int) config('abuse.progressive_penalties.multiplier', 2));
        $maximum = max($base, (int) config('abuse.progressive_penalties.maximum_seconds', 3600));
        $steps = max(0, intdiv($riskScore, 20) - 1);

        return min($maximum, $base * ($multiplier ** $steps));
    }
}
