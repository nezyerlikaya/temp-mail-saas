<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Free', 'slug' => 'free', 'sort_order' => 10],
            ['name' => 'Member', 'slug' => 'member', 'sort_order' => 20],
            ['name' => 'Premium', 'slug' => 'premium', 'sort_order' => 30],
        ] as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'uuid' => Plan::query()->where('slug', $plan['slug'])->value('uuid') ?: (string) Str::uuid(),
                    'name' => $plan['name'],
                    'description' => null,
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $plan['sort_order'],
                ],
            );
        }
    }
}
