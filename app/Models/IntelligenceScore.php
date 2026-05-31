<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'score_type',
    'reference_type',
    'reference_id',
    'score',
    'calculated_at',
    'metadata',
])]
class IntelligenceScore extends Model
{
    public function clampedScore(): int
    {
        return max(0, min(100, $this->score));
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'calculated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
