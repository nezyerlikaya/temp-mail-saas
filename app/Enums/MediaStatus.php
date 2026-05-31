<?php

namespace App\Enums;

enum MediaStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Active = 'active';
    case Failed = 'failed';
    case Deleted = 'deleted';
}
