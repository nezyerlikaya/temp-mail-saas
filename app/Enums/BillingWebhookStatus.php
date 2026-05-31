<?php

namespace App\Enums;

enum BillingWebhookStatus: string
{
    case Received = 'received';
    case Verified = 'verified';
    case Processed = 'processed';
    case Failed = 'failed';
    case Rejected = 'rejected';
}
