<?php

namespace App\Console\Commands;

use App\Services\Mail\FirstRealMailValidationService;
use App\Services\Mail\MailReceptionTraceService;
use Illuminate\Console\Command;

class MailFirstRealCheckCommand extends Command
{
    protected $signature = 'mail:first-real-check
        {--provider= : Provider to validate}
        {--domain= : Domain to validate}
        {--mailbox= : Mailbox address to trace}
        {--intake= : Inbound intake UUID to trace}
        {--message= : Email message UUID to trace}';

    protected $description = 'Run safe first real mail reception readiness and trace diagnostics.';

    public function handle(FirstRealMailValidationService $validation, MailReceptionTraceService $trace): int
    {
        $report = $validation->report(
            provider: $this->optionString('provider'),
            domain: $this->optionString('domain'),
            mailbox: $this->optionString('mailbox'),
        );

        $this->info('First real mail validation: '.strtoupper($report['status']));
        $this->line('Passed: '.count($report['passed']));
        $this->line('Blockers: '.count($report['blockers']));
        $this->line('Warnings: '.count($report['warnings']));
        $this->line('Recommendations: '.count($report['recommendations']));

        foreach (['blockers' => 'Blocker', 'warnings' => 'Warning'] as $key => $label) {
            foreach ($report[$key] as $item) {
                $this->line("{$label}: {$item['name']} - {$item['message']}");
            }
        }

        if ($this->hasTraceInput()) {
            $diagnostic = $trace->trace(
                intakeUuid: $this->optionString('intake'),
                messageUuid: $this->optionString('message'),
                mailboxAddress: $this->optionString('mailbox'),
            );

            $this->line('Trace status: '.strtoupper($diagnostic['status']));
            foreach ($diagnostic['lifecycle'] as $stage => $passed) {
                $this->line('Trace '.$stage.': '.($passed ? 'yes' : 'no'));
            }
        }

        return $report['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }

    private function hasTraceInput(): bool
    {
        return $this->optionString('intake') !== null
            || $this->optionString('message') !== null
            || $this->optionString('mailbox') !== null;
    }

    private function optionString(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
