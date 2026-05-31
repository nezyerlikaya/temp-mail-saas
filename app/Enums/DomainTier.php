<?php

namespace App\Enums;

enum DomainTier: string
{
    case Free = 'free';
    case Member = 'member';
    case Premium = 'premium';
    case Enterprise = 'enterprise';
}
