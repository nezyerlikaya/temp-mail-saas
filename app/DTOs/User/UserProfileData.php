<?php

namespace App\DTOs\User;

use App\DTOs\DataTransferObject;
use App\Enums\AccountTier;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\User\AvatarMetadataService;

final readonly class UserProfileData extends DataTransferObject
{
    public function __construct(
        public int $id,
        public ?string $username,
        public ?string $displayName,
        public ?string $publicSlug,
        public string $avatarUrl,
        public AccountTier $accountTier,
        public UserStatus $status,
    ) {
    }

    public static function fromUser(User $user, AvatarMetadataService $avatars): self
    {
        return new self(
            id: $user->getKey(),
            username: $user->username,
            displayName: $user->display_name,
            publicSlug: $user->public_slug,
            avatarUrl: $avatars->url($user),
            accountTier: $user->account_tier,
            status: $user->status,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->displayName,
            'public_slug' => $this->publicSlug,
            'avatar_url' => $this->avatarUrl,
            'account_tier' => $this->accountTier->value,
            'status' => $this->status->value,
        ];
    }
}
