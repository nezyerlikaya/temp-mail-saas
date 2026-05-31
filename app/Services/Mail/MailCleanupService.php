<?php

namespace App\Services\Mail;

use App\Enums\CleanupRunStatus;
use App\Enums\CleanupRunType;
use App\Enums\EmailMessageStatus;
use App\Enums\InboundIntakeStatus;
use App\Models\CleanupRun;
use App\Models\EmailMessage;
use App\Models\InboundMailIntake;
use App\Services\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

final class MailCleanupService extends Service
{
    public function __construct(
        private readonly EmailRetentionService $retention,
    ) {}

    public function runFullCleanup(?bool $dryRun = null, ?int $chunkSize = null): array
    {
        $dryRun ??= (bool) config('retention.cleanup_dry_run_default', false);
        $chunkSize = $this->chunkSize($chunkSize);
        $run = $this->startRun(CleanupRunType::Full, $dryRun);

        try {
            $summary = $this->emptySummary($dryRun, $chunkSize);
            $summary = $this->mergeSummaries($summary, $this->cleanupExpiredMessages($dryRun, $chunkSize));
            $summary = $this->mergeSummaries($summary, $this->cleanupExpiredIntakes($dryRun, $chunkSize));

            $this->completeRun($run, $summary);

            return $summary;
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);

            throw $exception;
        }
    }

    public function cleanupExpiredMessages(?bool $dryRun = null, ?int $chunkSize = null): array
    {
        $dryRun ??= (bool) config('retention.cleanup_dry_run_default', false);
        $chunkSize = $this->chunkSize($chunkSize);
        $summary = $this->emptySummary($dryRun, $chunkSize);
        $hardDelete = (bool) config('retention.hard_delete_enabled', false);

        $this->retention->expiredMessagesQuery()
            ->withCount(['attachments', 'recipients'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($messages) use (&$summary, $dryRun, $hardDelete): void {
                foreach ($messages as $message) {
                    $summary['messages_scanned']++;
                    $summary['attachments_affected'] += (int) $message->attachments_count;

                    if ($dryRun) {
                        $summary['messages_expired']++;

                        continue;
                    }

                    if ($hardDelete) {
                        $this->hardDeleteMessage($message);
                        $summary['messages_deleted']++;

                        continue;
                    }

                    $message->forceFill([
                        'status' => EmailMessageStatus::Expired->value,
                    ])->save();
                    $summary['messages_expired']++;
                }
            });

        return $summary;
    }

    public function cleanupExpiredIntakes(?bool $dryRun = null, ?int $chunkSize = null): array
    {
        $dryRun ??= (bool) config('retention.cleanup_dry_run_default', false);
        $chunkSize = $this->chunkSize($chunkSize);
        $summary = $this->emptySummary($dryRun, $chunkSize);

        $this->expiredIntakesQuery()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($intakes) use (&$summary, $dryRun): void {
                foreach ($intakes as $intake) {
                    $summary['intakes_deleted']++;

                    if (! $dryRun) {
                        $intake->delete();
                    }
                }
            });

        return $summary;
    }

    public function expiredIntakesQuery(): Builder
    {
        $minutes = max(1, (int) config('retention.intake_retention_minutes', 10080));

        return InboundMailIntake::query()
            ->whereIn('status', [
                InboundIntakeStatus::Processed->value,
                InboundIntakeStatus::Failed->value,
                InboundIntakeStatus::Rejected->value,
            ])
            ->where('updated_at', '<=', now()->subMinutes($minutes));
    }

    private function hardDeleteMessage(EmailMessage $message): void
    {
        $message->attachments()->delete();
        $message->recipients()->delete();
        $message->forceDelete();
    }

    private function startRun(CleanupRunType $type, bool $dryRun): ?CleanupRun
    {
        if (! config('retention.cleanup_log_enabled', true)) {
            return null;
        }

        return CleanupRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'status' => CleanupRunStatus::Running,
            'dry_run' => $dryRun,
            'started_at' => now(),
        ]);
    }

    private function completeRun(?CleanupRun $run, array $summary): void
    {
        $run?->forceFill([
            'status' => CleanupRunStatus::Completed->value,
            'messages_scanned' => $summary['messages_scanned'],
            'messages_expired' => $summary['messages_expired'],
            'messages_deleted' => $summary['messages_deleted'],
            'intakes_deleted' => $summary['intakes_deleted'],
            'attachments_affected' => $summary['attachments_affected'],
            'finished_at' => now(),
        ])->save();
    }

    private function failRun(?CleanupRun $run, Throwable $exception): void
    {
        $run?->forceFill([
            'status' => CleanupRunStatus::Failed->value,
            'error_message' => Str::limit(class_basename($exception), 500, ''),
            'finished_at' => now(),
        ])->save();
    }

    private function emptySummary(bool $dryRun, int $chunkSize): array
    {
        return [
            'dry_run' => $dryRun,
            'chunk_size' => $chunkSize,
            'messages_scanned' => 0,
            'messages_expired' => 0,
            'messages_deleted' => 0,
            'intakes_deleted' => 0,
            'attachments_affected' => 0,
        ];
    }

    private function mergeSummaries(array $summary, array $addition): array
    {
        foreach ([
            'messages_scanned',
            'messages_expired',
            'messages_deleted',
            'intakes_deleted',
            'attachments_affected',
        ] as $key) {
            $summary[$key] += $addition[$key];
        }

        return $summary;
    }

    private function chunkSize(?int $chunkSize): int
    {
        return max(1, min(1000, $chunkSize ?? (int) config('retention.cleanup_chunk_size', 100)));
    }
}
