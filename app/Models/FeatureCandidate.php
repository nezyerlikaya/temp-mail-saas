<?php

namespace App\Models;

use App\Enums\FeatureCandidateCategory;
use App\Enums\FeatureCandidateEffort;
use App\Enums\FeatureCandidatePriority;
use App\Enums\FeatureCandidateRisk;
use App\Enums\FeatureCandidateStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'title',
    'description',
    'category',
    'priority',
    'status',
    'effort',
    'impact',
    'risk',
    'metadata',
])]
class FeatureCandidate extends Model
{
    public function isAccepted(): bool
    {
        return $this->status === FeatureCandidateStatus::Accepted;
    }

    public function isDeferred(): bool
    {
        return $this->status === FeatureCandidateStatus::Deferred;
    }

    protected function casts(): array
    {
        return [
            'category' => FeatureCandidateCategory::class,
            'priority' => FeatureCandidatePriority::class,
            'status' => FeatureCandidateStatus::class,
            'effort' => FeatureCandidateEffort::class,
            'risk' => FeatureCandidateRisk::class,
            'metadata' => 'array',
        ];
    }
}
