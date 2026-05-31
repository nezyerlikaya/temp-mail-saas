<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'language_id',
    'group',
    'key',
    'value',
    'is_custom',
])]
class Translation extends Model
{
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
        ];
    }
}
