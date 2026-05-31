<?php

namespace App\Enums;

enum SystemHealthStatus: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Critical = 'critical';
}
