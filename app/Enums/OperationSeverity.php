<?php

namespace App\Enums;

enum OperationSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
}
