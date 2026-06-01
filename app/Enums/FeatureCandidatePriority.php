<?php

namespace App\Enums;

enum FeatureCandidatePriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
