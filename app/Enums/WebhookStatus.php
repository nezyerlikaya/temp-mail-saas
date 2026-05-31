<?php

namespace App\Enums;

enum WebhookStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';
}
