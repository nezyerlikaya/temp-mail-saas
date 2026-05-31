<?php

namespace App\Enums;

enum DomainType: string
{
    case Public = 'public';
    case Premium = 'premium';
    case Enterprise = 'enterprise';
}
