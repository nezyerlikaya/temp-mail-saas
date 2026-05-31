<?php

namespace App\Services\Mail;

use App\Models\InboundMailIntake;
use App\Models\QueueMetric;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LoadReadinessService extends Service
{
    public function report(): array
    {
        return [
            'queue' => $this->queueCapacity(),
            'intake' => $this->intakeThroughput(),
            'cleanup' => $this->cleanupThroughput(),
            'providers' => $this->providerThroughput(),
        ];
    }

    private function queueCapacity(): array
    {
        $queue = (string) config('inbound.queue.name', 'inbound-mail');
        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->where('queue', $queue)->count() : 0;
        $threshold = (int) config('mail-providers.throughput.queue_pending_warning', 100);

        return [
            'queue' => $queue,
            'pending_jobs' => $pending,
            'threshold' => $threshold,
            'status' => $pending >= $threshold ? 'warning' : 'ready',
        ];
    }

    private function intakeThroughput(): array
    {
        $count = InboundMailIntake::query()->where('created_at', '>=', now()->subMinute())->count();
        $threshold = (int) config('mail-providers.throughput.intake_per_minute_warning', 120);

        return [
            'intakes_last_minute' => $count,
            'threshold' => $threshold,
            'status' => $count >= $threshold ? 'warning' : 'ready',
        ];
    }

    private function cleanupThroughput(): array
    {
        $chunk = (int) config('retention.cleanup_chunk_size', 100);
        $recommended = (int) config('mail-providers.throughput.cleanup_chunk_recommendation', 100);

        return [
            'chunk_size' => $chunk,
            'recommended_minimum' => $recommended,
            'status' => $chunk >= $recommended ? 'ready' : 'recommendation',
        ];
    }

    private function providerThroughput(): array
    {
        return InboundMailIntake::query()
            ->select('provider', DB::raw('count(*) as total'))
            ->groupBy('provider')
            ->pluck('total', 'provider')
            ->all();
    }
}
