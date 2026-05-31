<?php

namespace App\Services\Automation;

use App\Models\AbuseEvent;
use App\Models\DomainHealthCheck;
use App\Models\IntelligenceScore;
use App\Models\QueueMetric;
use App\Services\Service;

final class IntelligenceService extends Service
{
    public function recordScore(string $scoreType, int $score, ?string $referenceType = null, ?int $referenceId = null, array $metadata = []): IntelligenceScore
    {
        return IntelligenceScore::query()->create([
            'score_type' => $scoreType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'score' => $this->clamp($score),
            'calculated_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public function scoreAbuseEvent(AbuseEvent $event): IntelligenceScore
    {
        $severityBoost = match ($event->severity->value) {
            'critical' => 40,
            'high' => 25,
            'medium' => 10,
            default => 0,
        };

        return $this->recordScore(
            'abuse_risk',
            (int) $event->risk_score + $severityBoost,
            $event::class,
            $event->id,
            ['event_type' => $event->event_type->value, 'severity' => $event->severity->value],
        );
    }

    public function scoreDomainHealth(DomainHealthCheck $check): IntelligenceScore
    {
        return $this->recordScore(
            'domain_health',
            (int) $check->score,
            $check::class,
            $check->id,
            ['status' => $check->status->value, 'domain' => $check->domain],
        );
    }

    public function scoreQueueHealth(QueueMetric $metric): IntelligenceScore
    {
        $penalty = min(100, ($metric->pending_jobs * 2) + ($metric->failed_jobs * 10));

        return $this->recordScore(
            'queue_health',
            100 - $penalty,
            $metric::class,
            $metric->id,
            ['queue_name' => $metric->queue_name],
        );
    }

    public function recalculateOperationalScores(): array
    {
        $scores = [];

        if ($abuse = AbuseEvent::query()->latest('occurred_at')->first()) {
            $scores[] = $this->scoreAbuseEvent($abuse);
        }

        if ($domain = DomainHealthCheck::query()->latest('checked_at')->first()) {
            $scores[] = $this->scoreDomainHealth($domain);
        }

        if ($queue = QueueMetric::query()->latest('measured_at')->first()) {
            $scores[] = $this->scoreQueueHealth($queue);
        }

        return $scores;
    }

    private function clamp(int $score): int
    {
        return max(0, min(100, $score));
    }
}
