<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'queue_name',
    'pending_jobs',
    'failed_jobs',
    'processed_jobs',
    'measured_at',
])]
class QueueMetric extends Model
{
    public function hasBacklog(): bool
    {
        return $this->pending_jobs > 0;
    }

    public function hasFailures(): bool
    {
        return $this->failed_jobs > 0;
    }

    protected function casts(): array
    {
        return [
            'pending_jobs' => 'integer',
            'failed_jobs' => 'integer',
            'processed_jobs' => 'integer',
            'measured_at' => 'datetime',
        ];
    }
}
