<?php

namespace App\Enums;

enum MonitoringAlertStatus: string
{
    case Active = 'active';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
}
