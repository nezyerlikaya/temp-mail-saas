<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'language_id',
    'staff_user_id',
    'action',
    'key',
    'old_value',
    'new_value',
    'created_at',
])]
class LocalizationAudit extends Model
{
    public const UPDATED_AT = null;

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
