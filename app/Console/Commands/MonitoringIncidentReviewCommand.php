<?php

namespace App\Console\Commands;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use Illuminate\Console\Command;

class MonitoringIncidentReviewCommand extends Command
{
    protected $signature = 'monitoring:incident-review';

    protected $description = 'Summarize open and critical incidents without exposing sensitive metadata.';

    public function handle(): int
    {
        $open = Incident::query()
            ->where('status', '!=', IncidentStatus::Resolved->value)
            ->latest('detected_at')
            ->get();
        $critical = $open->where('severity', IncidentSeverity::Critical);

        $this->info('Monitoring incident review');
        $this->line('Open incidents: '.$open->count());
        $this->line('Critical incidents: '.$critical->count());

        $open->take(10)->each(function (Incident $incident): void {
            $this->line("- {$incident->severity->value}: {$incident->title} ({$incident->status->value})");
        });

        return $critical->isNotEmpty() ? self::FAILURE : self::SUCCESS;
    }
}
