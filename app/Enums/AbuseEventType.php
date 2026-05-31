<?php

namespace App\Enums;

enum AbuseEventType: string
{
    case MailboxGeneration = 'mailbox_generation';
    case MailboxRotation = 'mailbox_rotation';
    case InboxPolling = 'inbox_polling';
    case MessageDetail = 'message_detail';
    case LoginAttempt = 'login_attempt';
    case RegistrationAttempt = 'registration_attempt';
    case InboundIntake = 'inbound_intake';
    case SuspiciousActivity = 'suspicious_activity';
}
