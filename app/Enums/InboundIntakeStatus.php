<?php

namespace App\Enums;

enum InboundIntakeStatus: string
{
    case Received = 'received';
    case Verified = 'verified';
    case Queued = 'queued';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Rejected = 'rejected';
}
