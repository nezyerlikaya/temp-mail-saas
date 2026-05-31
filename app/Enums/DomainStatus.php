<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
    case Suspended = 'suspended';
}
