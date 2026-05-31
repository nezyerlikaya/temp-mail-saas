<?php

namespace App\Models;

use App\Enums\ApiKeyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'user_id',
    'name',
    'key_prefix',
    'key_hash',
    'status',
    'last_used_at',
    'expires_at',
    'revoked_at',
    'metadata',
])]
class ApiKey extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === ApiKeyStatus::Active && ! $this->isExpired() && ! $this->isRevoked();
    }

    public function isExpired(): bool
    {
        return $this->status === ApiKeyStatus::Expired
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function isRevoked(): bool
    {
        return $this->status === ApiKeyStatus::Revoked || $this->revoked_at !== null;
    }

    protected function casts(): array
    {
        return [
            'status' => ApiKeyStatus::class,
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
