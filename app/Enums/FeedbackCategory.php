<?php

namespace App\Enums;

enum FeedbackCategory: string
{
    case Platform = 'platform';
    case Inbox = 'inbox';
    case Billing = 'billing';
    case Analytics = 'analytics';
    case Operations = 'operations';
    case Growth = 'growth';
    case Enterprise = 'enterprise';
    case Other = 'other';
}
