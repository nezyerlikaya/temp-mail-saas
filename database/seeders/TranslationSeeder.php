<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            'en' => [
                'app' => [
                    'name' => 'Temp Mail SaaS',
                    'status' => 'Service status',
                ],
            ],
            'tr' => [
                'app' => [
                    'name' => 'Temp Mail SaaS',
                    'status' => 'Servis durumu',
                ],
            ],
        ];

        foreach ($translations as $locale => $groups) {
            $language = Language::query()->where('code', $locale)->first();

            if ($language === null) {
                continue;
            }

            foreach ($groups as $group => $items) {
                foreach ($items as $key => $value) {
                    Translation::query()->updateOrCreate(
                        [
                            'language_id' => $language->id,
                            'group' => $group,
                            'key' => $key,
                        ],
                        [
                            'value' => $value,
                            'is_custom' => false,
                        ],
                    );
                }
            }
        }
    }
}
