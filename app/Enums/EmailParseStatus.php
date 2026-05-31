<?php

namespace App\Enums;

enum EmailParseStatus: string
{
    case Pending = 'pending';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case Failed = 'failed';
}
