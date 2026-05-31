<?php

namespace App\Models;

use App\Enums\UserIntegrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'integration_id',
    'user_id',
    'organization_id',
    'status',
    'configuration',
    'connected_at',
    'disconnected_at',
])]
#[Hidden(['configuration'])]
class UserIntegration extends Model
{
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isConnected(): bool
    {
        return $this->status === UserIntegrationStatus::Connected;
    }

    protected function casts(): array
    {
        return [
            'status' => UserIntegrationStatus::class,
            'configuration' => 'encrypted:array',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }
}
