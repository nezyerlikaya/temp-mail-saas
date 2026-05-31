<?php

namespace App\Enums;

enum InboundProvider: string
{
    case Local = 'local';
    case Mailgun = 'mailgun';
    case Postmark = 'postmark';
    case Ses = 'ses';
    case Custom = 'custom';
}
