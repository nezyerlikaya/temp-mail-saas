<?php

namespace App\Enums;

enum RetentionTier: string
{
    case Short = 'short';
    case Standard = 'standard';
    case Premium = 'premium';
}
