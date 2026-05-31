<?php

namespace App\Services\Operations;

use App\Models\Incident;
use App\Models\MonitoringAlert;
use App\Services\Service;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class UptimeReadinessService extends Service
{
    public function report(): array
    {
        $checks = [
            'health_endpoint' => Route::has('health'),
            'status_endpoint' => Route::has('status'),
            'incident_tracking' => Schema::hasTable('incidents'),
            'alert_tracking' => Schema::hasTable('monitoring_alerts'),
            'operations_events' => Schema::hasTable('operations_events'),
        ];

        $ready = ! in_array(false, $checks, true);

        return [
            'status' => $ready ? 'ready' : 'warning',
            'checks' => $checks,
            'open_incidents' => Schema::hasTable('incidents') ? Incident::query()->where('status', '!=', 'resolved')->count() : 0,
            'active_alerts' => Schema::hasTable('monitoring_alerts') ? MonitoringAlert::query()->where('status', 'active')->count() : 0,
        ];
    }
}
