<?php

namespace Database\Seeders;

use App\Enums\LanguageDirection;
use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        Language::query()->update(['is_default' => false]);

        Language::query()->updateOrCreate(
            ['code' => 'en'],
            [
                'name' => 'English',
                'native_name' => 'English',
                'direction' => LanguageDirection::Ltr,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 10,
            ],
        );

        Language::query()->updateOrCreate(
            ['code' => 'tr'],
            [
                'name' => 'Turkish',
                'native_name' => 'Turkce',
                'direction' => LanguageDirection::Ltr,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 20,
            ],
        );
    }
}
