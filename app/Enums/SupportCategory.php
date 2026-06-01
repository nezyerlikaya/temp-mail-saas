<?php

namespace App\Enums;

enum SupportCategory: string
{
    case Account = 'account';
    case Inbox = 'inbox';
    case Billing = 'billing';
    case Domain = 'domain';
    case Provider = 'provider';
    case Abuse = 'abuse';
    case Api = 'api';
    case Other = 'other';
}
