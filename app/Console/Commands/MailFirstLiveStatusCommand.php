<?php

namespace App\Console\Commands;

use App\Services\Mail\FirstLiveMailReadinessService;
use Illuminate\Console\Command;

class MailFirstLiveStatusCommand extends Command
{
    protected $signature = 'mail:first-live-status
        {--provider= : Provider to inspect}
        {--domain= : Domain to inspect}
        {--mailbox= : Mailbox to inspect}
        {--intake= : Inbound intake UUID to trace}
        {--provider-message= : Provider message id to trace}
        {--message= : Email message UUID to trace}';

    protected $description = 'Display safe first live production mail reception readiness.';

    public function handle(FirstLiveMailReadinessService $readiness): int
    {
        $report = $readiness->report(
            provider: $this->optionString('provider'),
            domain: $this->optionString('domain'),
            mailbox: $this->optionString('mailbox'),
            intakeUuid: $this->optionString('intake'),
            providerMessageId: $this->optionString('provider-message'),
            messageUuid: $this->optionString('message'),
        );

        $this->info('First live mail readiness: '.strtoupper($report['status']));
        $this->line('Blockers: '.count($report['blockers']));
        $this->line('Warnings: '.count($report['warnings']));
        $this->line('Diagnostics: '.strtoupper($report['diagnostics']['status']));
        $this->line('Trace readiness: '.strtoupper($report['trace']['status']));
        $this->line('Recommendations: '.count($report['recommendations']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($report[$key] as $item) {
                $this->line("{$label}: {$item['category']}.{$item['name']} - {$item['message']}");
            }
        }

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }

    private function optionString(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
