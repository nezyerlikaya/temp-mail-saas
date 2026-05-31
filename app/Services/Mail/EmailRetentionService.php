<?php

namespace App\Services\Mail;

use App\Enums\EmailMessageStatus;
use App\Enums\RetentionTier;
use App\Models\EmailMessage;
use App\Models\User;
use App\Services\Billing\FeatureGateService;
use App\Services\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class EmailRetentionService extends Service
{
    public function __construct(
        private readonly FeatureGateService $features,
    ) {}

    public function defaultTier(): RetentionTier
    {
        return RetentionTier::tryFrom((string) config('retention.email.default_tier', RetentionTier::Standard->value))
            ?? RetentionTier::Standard;
    }

    public function expirationFor(RetentionTier|string|null $tier = null, ?Carbon $from = null): Carbon
    {
        $tier = $tier instanceof RetentionTier
            ? $tier
            : (RetentionTier::tryFrom((string) $tier) ?? $this->defaultTier());

        $minutes = (int) config("retention.email.tiers.{$tier->value}", 1440);

        return ($from ?? now())->copy()->addMinutes(max(1, $minutes));
    }

    public function tierForUser(?User $user = null): RetentionTier
    {
        return RetentionTier::tryFrom((string) $this->features->featureValue(
            'retention_tier',
            $user,
            $this->defaultTier()->value,
        )) ?? $this->defaultTier();
    }

    public function determineExpirationDate(RetentionTier|string|null $tier = null, ?Carbon $from = null): Carbon
    {
        return $this->expirationFor($tier, $from);
    }

    public function isExpired(EmailMessage $message, ?Carbon $now = null): bool
    {
        return $message->expires_at !== null && $message->expires_at->lte($now ?? now());
    }

    public function expiredMessagesQuery(?Carbon $now = null): Builder
    {
        return EmailMessage::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now ?? now())
            ->whereNotIn('status', [
                EmailMessageStatus::Expired,
                EmailMessageStatus::Deleted,
            ]);
    }
}
