<?php

namespace App\Enums;

enum OperationCategory: string
{
    case System = 'system';
    case Queue = 'queue';
    case Domain = 'domain';
    case Abuse = 'abuse';
    case Api = 'api';
    case Mail = 'mail';
    case Scheduler = 'scheduler';
}
