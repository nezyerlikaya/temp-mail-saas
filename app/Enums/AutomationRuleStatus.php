<?php

namespace App\Enums;

enum AutomationRuleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Paused = 'paused';
}
