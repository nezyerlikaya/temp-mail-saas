<?php

namespace App\Console\Commands;

use App\Enums\EmailMessageStatus;
use App\Services\Mail\EmailRetentionService;
use Illuminate\Console\Command;

class CleanupExpiredMailCommand extends Command
{
    protected $signature = 'mail:cleanup-expired {--dry-run : Show what would be processed without changing records}';

    protected $description = 'Mark or soft-delete expired email message records without touching attachment files.';

    public function handle(EmailRetentionService $retention): int
    {
        $chunkSize = (int) config('retention.cleanup_chunk_size', 100);
        $action = (string) config('retention.expired_message_action', 'mark');
        $processed = 0;

        $retention->expiredMessagesQuery()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($messages) use (&$processed, $action): void {
                foreach ($messages as $message) {
                    $processed++;

                    if ($this->option('dry-run')) {
                        continue;
                    }

                    if ($action === 'delete') {
                        $message->forceFill([
                            'status' => EmailMessageStatus::Deleted->value,
                        ])->save();
                        $message->delete();

                        continue;
                    }

                    $message->forceFill([
                        'status' => EmailMessageStatus::Expired->value,
                    ])->save();
                }
            });

        $this->info("Expired email messages processed: {$processed}");

        if ($action !== 'delete') {
            $this->line('Action: marked expired.');
        } else {
            $this->line('Action: soft-deleted expired records.');
        }

        return self::SUCCESS;
    }
}
