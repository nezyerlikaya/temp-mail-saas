<?php

namespace App\Console\Commands;

use App\Services\Mail\MailCleanupService;
use Illuminate\Console\Command;

class CleanupExpiredMailCommand extends Command
{
    protected $signature = 'mail:cleanup-expired
        {--dry-run : Show what would be processed without changing records}
        {--chunk= : Number of records to process per chunk}';

    protected $description = 'Clean expired mail and inbound intake records without exposing private content.';

    public function handle(MailCleanupService $cleanup): int
    {
        $dryRun = $this->option('dry-run') || (bool) config('retention.cleanup_dry_run_default', false);
        $chunk = $this->option('chunk');
        $chunk = is_numeric($chunk) ? (int) $chunk : null;
        $summary = $cleanup->runFullCleanup($dryRun, $chunk);

        $this->info($summary['dry_run'] ? 'Cleanup dry-run completed.' : 'Cleanup completed.');
        $this->line("Expired email messages processed: {$summary['messages_scanned']}");
        $this->line("Messages scanned: {$summary['messages_scanned']}");
        $this->line("Messages expired: {$summary['messages_expired']}");
        $this->line("Messages deleted: {$summary['messages_deleted']}");
        $this->line("Intakes deleted: {$summary['intakes_deleted']}");
        $this->line("Attachments affected: {$summary['attachments_affected']}");
        $this->line(config('retention.hard_delete_enabled', false)
            ? 'Action: hard-deleted expired records.'
            : 'Action: marked expired.');

        return self::SUCCESS;
    }
}
