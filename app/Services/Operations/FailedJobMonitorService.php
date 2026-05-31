<?php

namespace App\Services\Operations;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FailedJobMonitorService extends Service
{
    public function summarize(bool $log = true): array
    {
        $total = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $byQueue = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')
                ->select('queue', DB::raw('count(*) as total'))
                ->groupBy('queue')
                ->pluck('total', 'queue')
                ->all()
            : [];

        if ($log && $total > 0) {
            app(OperationsLoggerService::class)->log(
                OperationCategory::Queue,
                'failed_jobs_detected',
                OperationSeverity::Error,
                source: 'failed-job-monitor',
                message: 'Failed jobs detected.',
                metadata: [
                    'total' => $total,
                    'queues' => $byQueue,
                ],
            );
        }

        return [
            'total_failed_jobs' => $total,
            'queues' => $byQueue,
        ];
    }
}
