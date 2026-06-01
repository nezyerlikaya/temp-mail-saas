<?php

namespace App\Services\Support;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Enums\SupportCategory;
use App\Enums\SupportPriority;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class SupportRequestService extends Service
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
        'request',
        'secret',
        'session',
        'token',
        'user_agent',
    ];

    public function __construct(private readonly OperationsLoggerService $operations) {}

    public function create(array $data, ?User $user = null, ?Organization $organization = null): SupportRequest
    {
        $classification = $this->classify($data);
        $request = SupportRequest::query()->create([
            'user_id' => $user?->getKey(),
            'organization_id' => $organization?->getKey(),
            'category' => $classification['category']->value,
            'priority' => $classification['priority']->value,
            'status' => SupportStatus::Open->value,
            'subject' => $this->sanitizeText((string) ($data['subject'] ?? 'Support request'), 255),
            'message' => $this->sanitizeText((string) ($data['message'] ?? ''), (int) config('support-intelligence.support.message_max_length', 4000)),
            'metadata' => $this->sanitizeMetadata($data['metadata'] ?? []),
        ]);

        $this->record('support_request_created', $request);

        return $request;
    }

    public function classify(array $data): array
    {
        return [
            'category' => $this->enum(SupportCategory::class, $data['category'] ?? null, SupportCategory::Other),
            'priority' => $this->enum(SupportPriority::class, $data['priority'] ?? null, SupportPriority::Medium),
        ];
    }

    public function updateStatus(SupportRequest $request, SupportStatus|string $status): SupportRequest
    {
        $status = $this->enum(SupportStatus::class, $status, SupportStatus::InProgress);
        $changes = ['status' => $status->value];

        if ($request->first_response_at === null && $status !== SupportStatus::Open) {
            $changes['first_response_at'] = now();
        }

        if (in_array($status, [SupportStatus::Resolved, SupportStatus::Closed], true)) {
            $changes['resolved_at'] = $request->resolved_at ?? now();
        }

        $request->forceFill($changes)->save();
        $this->record(in_array($status, [SupportStatus::Resolved, SupportStatus::Closed], true) ? 'support_request_resolved' : 'support_request_updated', $request);

        return $request->refresh();
    }

    public function metrics(): array
    {
        $resolved = SupportRequest::query()->whereNotNull('resolved_at')->get();
        $responded = SupportRequest::query()->whereNotNull('first_response_at')->get();

        return [
            'average_response_minutes' => $this->averageMinutes($responded, 'first_response_at'),
            'average_resolution_minutes' => $this->averageMinutes($resolved, 'resolved_at'),
            'open_requests' => SupportRequest::query()->whereNotIn('status', [SupportStatus::Resolved, SupportStatus::Closed])->count(),
        ];
    }

    public function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->take((int) config('support-intelligence.support.metadata_limit', 20))
            ->reject(fn (mixed $value, string|int $key): bool => $this->sensitiveKey((string) $key))
            ->map(fn (mixed $value): mixed => is_array($value)
                ? $this->sanitizeMetadata($value)
                : (is_string($value) ? $this->sanitizeText($value, 255) : (is_scalar($value) || $value === null ? $value : null)))
            ->all();
    }

    private function averageMinutes(iterable $requests, string $column): float
    {
        return round((float) collect($requests)
            ->map(fn (SupportRequest $request): int => $request->created_at->diffInMinutes($request->{$column}))
            ->average(), 2);
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

    private function record(string $eventType, SupportRequest $request): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            OperationSeverity::Info,
            OperationStatus::Detected,
            'support-intelligence',
            'Support request event recorded.',
            [
                'support_request_id' => $request->getKey(),
                'category' => $request->category->value,
                'priority' => $request->priority->value,
                'status' => $request->status->value,
            ],
        );
    }
}
