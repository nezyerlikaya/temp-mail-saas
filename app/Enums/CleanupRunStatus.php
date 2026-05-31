<?php

namespace App\Enums;

enum CleanupRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
