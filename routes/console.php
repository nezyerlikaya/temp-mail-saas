<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('retention.schedule.enabled', false)) {
    $cleanup = Schedule::command('mail:cleanup-expired')
        ->withoutOverlapping();

    match ((string) config('retention.schedule.frequency', 'hourly')) {
        'daily' => $cleanup->daily(),
        default => $cleanup->hourly(),
    };
}
