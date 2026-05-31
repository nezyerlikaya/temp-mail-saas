<?php

namespace App\Enums;

enum AbuseStatus: string
{
    case Observed = 'observed';
    case Throttled = 'throttled';
    case Blocked = 'blocked';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
}
