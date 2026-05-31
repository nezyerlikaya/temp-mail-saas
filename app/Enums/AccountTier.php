<?php

namespace App\Enums;

enum AccountTier: string
{
    case Free = 'free';
    case Member = 'member';
    case Premium = 'premium';
}
