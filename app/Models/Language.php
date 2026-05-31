<?php

namespace App\Models;

use App\Enums\LanguageDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'native_name',
    'direction',
    'is_active',
    'is_default',
    'sort_order',
])]
class Language extends Model
{
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    public function isDefault(): bool
    {
        return $this->is_default === true;
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isRtl(): bool
    {
        return $this->direction === LanguageDirection::Rtl;
    }

    public function isLtr(): bool
    {
        return $this->direction === LanguageDirection::Ltr;
    }

    protected static function booted(): void
    {
        static::saved(function (Language $language): void {
            if (! $language->isDefault()) {
                return;
            }

            static::query()
                ->whereKeyNot($language->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    protected function casts(): array
    {
        return [
            'direction' => LanguageDirection::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
