<?php

namespace App\Enums;

enum UserIntegrationStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Suspended = 'suspended';
}
