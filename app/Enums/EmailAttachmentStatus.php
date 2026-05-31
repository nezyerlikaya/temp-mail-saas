<?php

namespace App\Enums;

enum EmailAttachmentStatus: string
{
    case Pending = 'pending';
    case Stored = 'stored';
    case Failed = 'failed';
    case Deleted = 'deleted';
}
