<?php

namespace App\Services\Abuse;

use App\Enums\AbuseEventType;
use App\Models\User;
use App\Services\Billing\FeatureGateService;
use App\Services\Service;

final class RateLimitProfileService extends Service
{
    public function __construct(
        private readonly FeatureGateService $features,
    ) {}

    public function for(AbuseEventType|string $action, ?User $user = null): array
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

        $perMinute = max(1, (int) ($profile['per_minute'] ?? config('abuse.rate_limits.per_minute', 60)));

        if ($action === AbuseEventType::MailboxGeneration->value) {
            $planLimit = max(1, (int) $this->features->featureValue('mailbox_generation_limit', $user, $perMinute));
            $perMinute = min($perMinute, $planLimit);
        }

        return [
            'action' => $action,
            'per_minute' => $perMinute,
            'cooldown_seconds' => max(1, (int) ($profile['cooldown_seconds'] ?? config('abuse.cooldown_seconds', 60))),
        ];
    }
}
