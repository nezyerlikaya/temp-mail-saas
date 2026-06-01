<?php

namespace App\Services\Roadmap;

use App\Enums\FeatureCandidateCategory;
use App\Enums\FeatureCandidateEffort;
use App\Enums\FeatureCandidatePriority;
use App\Enums\FeatureCandidateRisk;
use App\Enums\FeatureCandidateStatus;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\FeatureCandidate;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class FeatureCandidateService extends Service
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'body',
        'content',
        'email',
        'headers',
        'mailbox',
        'password',
        'payload',
        'raw',
        'secret',
        'token',
    ];

    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function create(array $data): FeatureCandidate
    {
        $candidate = FeatureCandidate::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => $this->sanitizeText((string) ($data['title'] ?? 'Feature candidate'), 255),
            'description' => isset($data['description']) ? $this->sanitizeText((string) $data['description'], 1000) : null,
            'category' => $this->enum(FeatureCandidateCategory::class, $data['category'] ?? null, FeatureCandidateCategory::Platform)->value,
            'priority' => $this->enum(FeatureCandidatePriority::class, $data['priority'] ?? null, FeatureCandidatePriority::Medium)->value,
            'status' => FeatureCandidateStatus::Proposed->value,
            'effort' => $this->enum(FeatureCandidateEffort::class, $data['effort'] ?? null, FeatureCandidateEffort::Medium)->value,
            'impact' => $this->enum(FeatureCandidatePriority::class, $data['impact'] ?? null, FeatureCandidatePriority::Medium)->value,
            'risk' => $this->enum(FeatureCandidateRisk::class, $data['risk'] ?? null, FeatureCandidateRisk::Low)->value,
            'metadata' => $this->sanitizeMetadata($data['metadata'] ?? []),
        ]);

        $this->record('feature_candidate_created', $candidate);

        return $candidate;
    }

    public function review(FeatureCandidate $candidate): FeatureCandidate
    {
        return $this->transition($candidate, FeatureCandidateStatus::Reviewed, 'feature_candidate_reviewed');
    }

    public function accept(FeatureCandidate $candidate): FeatureCandidate
    {
        return $this->transition($candidate, FeatureCandidateStatus::Accepted, 'feature_candidate_accepted');
    }

    public function defer(FeatureCandidate $candidate): FeatureCandidate
    {
        return $this->transition($candidate, FeatureCandidateStatus::Deferred, 'feature_candidate_deferred');
    }

    public function reject(FeatureCandidate $candidate): FeatureCandidate
    {
        return $this->transition($candidate, FeatureCandidateStatus::Rejected, 'feature_candidate_reviewed');
    }

    public function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->take((int) config('v11-roadmap.candidate_review.metadata_limit', 20))
            ->reject(fn (mixed $value, string|int $key): bool => $this->sensitiveKey((string) $key))
            ->map(fn (mixed $value): mixed => is_array($value)
                ? $this->sanitizeMetadata($value)
                : (is_string($value) ? $this->sanitizeText($value, 255) : (is_scalar($value) || $value === null ? $value : null)))
            ->all();
    }

    private function transition(FeatureCandidate $candidate, FeatureCandidateStatus $status, string $event): FeatureCandidate
    {
        $candidate->forceFill(['status' => $status->value])->save();
        $this->record($event, $candidate);

        return $candidate->refresh();
    }

    private function sanitizeText(string $value, int $limit): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $value) ?? $value;

        return Str::limit(trim($value), max(1, $limit), '');
    }

    private function sensitiveKey(string $key): bool
    {
        return Arr::first(self::SENSITIVE_KEYS, fn (string $sensitive): bool => str_contains(Str::lower($key), $sensitive)) !== null;
    }

    private function enum(string $enum, mixed $value, object $fallback): object
    {
        return $value instanceof $enum ? $value : ($enum::tryFrom((string) $value) ?? $fallback);
    }

    private function record(string $eventType, FeatureCandidate $candidate): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            OperationSeverity::Info,
            OperationStatus::Detected,
            'v11-roadmap',
            'v1.1 feature candidate event recorded.',
            [
                'candidate_id' => $candidate->getKey(),
                'category' => $candidate->category->value,
                'priority' => $candidate->priority->value,
                'status' => $candidate->status->value,
            ],
        );
    }
}
