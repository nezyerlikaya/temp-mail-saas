<?php

namespace App\Enums;

enum OperationStatus: string
{
    case Detected = 'detected';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
}
