<?php

namespace App\Enums;

enum ApiKeyStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
