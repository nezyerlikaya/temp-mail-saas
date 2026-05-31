<?php

namespace Database\Seeders;

use App\Enums\DomainAssignmentStrategy;
use App\Enums\DomainStatus;
use App\Enums\DomainTier;
use App\Enums\DomainType;
use App\Models\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DomainSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['domain' => 'example-temp.test', 'tier' => DomainTier::Free, 'type' => DomainType::Public, 'priority' => 100],
            ['domain' => 'member-temp.test', 'tier' => DomainTier::Member, 'type' => DomainType::Public, 'priority' => 80],
            ['domain' => 'premium-temp.test', 'tier' => DomainTier::Premium, 'type' => DomainType::Premium, 'priority' => 60],
        ] as $data) {
            Domain::query()->updateOrCreate(
                ['domain' => $data['domain']],
                [
                    'uuid' => Domain::query()->where('domain', $data['domain'])->value('uuid') ?: (string) Str::uuid(),
                    'status' => DomainStatus::Active,
                    'type' => $data['type'],
                    'tier' => $data['tier'],
                    'priority' => $data['priority'],
                    'health_score' => 100,
                    'assignment_strategy' => DomainAssignmentStrategy::HealthBased,
                    'metadata' => ['demo' => true],
                    'last_checked_at' => now(),
                ],
            );
        }
    }
}
