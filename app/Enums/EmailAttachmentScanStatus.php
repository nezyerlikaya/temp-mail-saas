<?php

namespace App\Enums;

enum EmailAttachmentScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Suspicious = 'suspicious';
    case Infected = 'infected';
    case Skipped = 'skipped';
}
