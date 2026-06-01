<?php

namespace App\Enums;

enum FeatureCandidateCategory: string
{
    case Inbox = 'inbox';
    case Billing = 'billing';
    case Admin = 'admin';
    case Api = 'api';
    case Automation = 'automation';
    case Enterprise = 'enterprise';
    case Growth = 'growth';
    case Operations = 'operations';
    case Platform = 'platform';
}
