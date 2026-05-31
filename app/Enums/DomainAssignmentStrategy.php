<?php

namespace App\Enums;

enum DomainAssignmentStrategy: string
{
    case Random = 'random';
    case Weighted = 'weighted';
    case Priority = 'priority';
    case HealthBased = 'health_based';
}
