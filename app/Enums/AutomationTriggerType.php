<?php

namespace App\Enums;

enum AutomationTriggerType: string
{
    case AbuseEvent = 'abuse_event';
    case QueueEvent = 'queue_event';
    case DomainEvent = 'domain_event';
    case BillingEvent = 'billing_event';
    case OperationsEvent = 'operations_event';
    case UserEvent = 'user_event';
    case ScheduledEvent = 'scheduled_event';
}
