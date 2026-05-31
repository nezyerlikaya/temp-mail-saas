<?php

namespace Database\Seeders;

use App\Models\SeoSetting;
use Illuminate\Database\Seeder;

class SeoSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'site_name' => config('seo.site_name', config('app.name')),
            'default_title' => config('seo.title'),
            'default_description' => config('seo.description'),
            'default_robots' => config('seo.robots'),
        ] as $key => $value) {
            SeoSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => 'defaults',
                    'is_public' => true,
                ],
            );
        }
    }
}
