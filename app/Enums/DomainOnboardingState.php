<?php

namespace App\Enums;

enum DomainOnboardingState: string
{
    case Draft = 'draft';
    case Validating = 'validating';
    case Ready = 'ready';
    case Active = 'active';
    case Suspended = 'suspended';
}
