<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'value',
    'group',
    'is_public',
])]
class SeoSetting extends Model
{
    public function isPublic(): bool
    {
        return $this->is_public === true;
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }
}
