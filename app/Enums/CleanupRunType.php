<?php

namespace App\Enums;

enum CleanupRunType: string
{
    case Mail = 'mail';
    case Inbound = 'inbound';
    case Attachments = 'attachments';
    case Full = 'full';
}
