<?php

namespace App\Services\ProductIntelligence;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Models\User;
use App\Models\UserFeedback;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class FeedbackService extends Service
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'body',
        'content',
        'cookie',
        'email',
        'headers',
        'ip',
        'mailbox',
        'password',
        'payload',
        'secret',
        'session',
        'token',
        'user_agent',
    ];

    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function create(array $data, ?User $user = null): UserFeedback
    {
        $classification = $this->classify($data);
        $feedback = UserFeedback::query()->create([
            'user_id' => $user?->getKey(),
            'type' => $classification['type']->value,
            'category' => $classification['category']->value,
            'priority' => $classification['priority']->value,
            'status' => FeedbackStatus::New->value,
            'title' => $this->sanitizeText((string) ($data['title'] ?? 'Feedback'), 255),
            'message' => $this->sanitizeText((string) ($data['message'] ?? ''), (int) config('product-intelligence.feedback.message_max_length', 4000)),
            'metadata' => $this->sanitizeMetadata($data['metadata'] ?? []),
        ]);

        $this->record('feedback_created', $feedback);

        return $feedback;
    }

    public function classify(array $data): array
    {
        return [
            'type' => $this->enum(FeedbackType::class, $data['type'] ?? null, FeedbackType::Suggestion),
            'category' => $this->enum(FeedbackCategory::class, $data['category'] ?? null, FeedbackCategory::Other),
            'priority' => $this->enum(FeedbackPriority::class, $data['priority'] ?? null, FeedbackPriority::Medium),
        ];
    }

    public function updateStatus(UserFeedback $feedback, FeedbackStatus|string $status): UserFeedback
    {
        $status = $this->enum(FeedbackStatus::class, $status, FeedbackStatus::Reviewed);
        $feedback->forceFill(['status' => $status->value])->save();

        $this->record($status === FeedbackStatus::Closed ? 'feedback_closed' : 'feedback_reviewed', $feedback);

        return $feedback->refresh();
    }

    public function aggregate(): array
    {
        return [
            'total' => UserFeedback::query()->count(),
            'open' => UserFeedback::query()->where('status', '!=', FeedbackStatus::Closed)->count(),
            'by_type' => $this->counts('type'),
            'by_category' => $this->counts('category'),
            'by_priority' => $this->counts('priority'),
            'by_status' => $this->counts('status'),
        ];
    }

    public function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->take((int) config('product-intelligence.feedback.metadata_limit', 20))
            ->reject(fn (mixed $value, string|int $key): bool => $this->sensitiveKey((string) $key))
            ->map(fn (mixed $value): mixed => is_array($value)
                ? $this->sanitizeMetadata($value)
                : (is_string($value) ? $this->sanitizeText($value, 255) : (is_scalar($value) || $value === null ? $value : null)))
            ->all();
    }

    private function counts(string $column): array
    {
        return UserFeedback::query()
            ->selectRaw("{$column}, count(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
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

    private function record(string $eventType, UserFeedback $feedback): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            OperationSeverity::Info,
            OperationStatus::Detected,
            'product-intelligence',
            'Product feedback event recorded.',
            [
                'feedback_id' => $feedback->getKey(),
                'type' => $feedback->type->value,
                'category' => $feedback->category->value,
                'priority' => $feedback->priority->value,
                'status' => $feedback->status->value,
            ],
        );
    }
}
