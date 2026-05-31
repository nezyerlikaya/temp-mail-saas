<?php

namespace App\Services\Abuse;

use App\Enums\AbuseEventType;
use App\Services\Service;

final class RateLimitProfileService extends Service
{
    public function for(AbuseEventType|string $action): array
    {
        $action = $action instanceof AbuseEventType ? $action->value : $action;
        $configKey = match ($action) {
            AbuseEventType::MailboxGeneration->value => 'mailbox_generation_limits',
            AbuseEventType::MailboxRotation->value => 'mailbox_rotation_limits',
            AbuseEventType::InboxPolling->value => 'polling_limits',
            AbuseEventType::MessageDetail->value => 'message_detail_limits',
            AbuseEventType::LoginAttempt->value => 'login_attempt_limits',
            AbuseEventType::RegistrationAttempt->value => 'registration_attempt_limits',
            default => null,
        };
        $profile = $configKey !== null ? config("abuse.{$configKey}", []) : [];

        return [
            'action' => $action,
            'per_minute' => max(1, (int) ($profile['per_minute'] ?? config('abuse.rate_limits.per_minute', 60))),
            'cooldown_seconds' => max(1, (int) ($profile['cooldown_seconds'] ?? config('abuse.cooldown_seconds', 60))),
        ];
    }
}
