<?php

namespace App\Enums;

enum EmailRecipientType: string
{
    case To = 'to';
    case Cc = 'cc';
    case Bcc = 'bcc';
}
