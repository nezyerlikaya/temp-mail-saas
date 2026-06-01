<?php

namespace App\Enums;

enum FeatureCandidateRisk: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
