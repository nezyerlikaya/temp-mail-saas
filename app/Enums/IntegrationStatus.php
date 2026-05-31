<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deprecated = 'deprecated';
}
