<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'api_key_id',
    'endpoint',
    'method',
    'response_status',
    'request_count',
    'occurred_at',
])]
class ApiUsageLog extends Model
{
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'request_count' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
