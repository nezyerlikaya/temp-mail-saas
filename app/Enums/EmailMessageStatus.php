<?php

namespace App\Enums;

enum EmailMessageStatus: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Processed = 'processed';
    case Failed = 'failed';
    case Quarantined = 'quarantined';
    case Expired = 'expired';
    case Deleted = 'deleted';
}
