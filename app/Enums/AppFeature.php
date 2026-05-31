<?php

namespace App\Enums;

enum AppFeature: string
{
    case Admin = 'admin';
    case Api = 'api';
    case Billing = 'billing';
    case CustomDomains = 'custom_domains';
    case UserAccounts = 'user_accounts';

    public function enabled(): bool
    {
        return (bool) config("features.{$this->value}", false);
    }
}
