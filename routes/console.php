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

if (config('production.health.schedule_enabled', false)) {
    $health = Schedule::command('system:health-check')
        ->withoutOverlapping();

    match ((string) config('production.health.schedule_frequency', 'hourly')) {
        'daily' => $health->daily(),
        default => $health->hourly(),
    };
}

if (config('operations.metrics.schedule_enabled', false)) {
    $operations = Schedule::command('operations:collect-metrics')
        ->withoutOverlapping();

    match ((string) config('operations.metrics.schedule_frequency', 'hourly')) {
        'daily' => $operations->daily(),
        default => $operations->hourly(),
    };
}
