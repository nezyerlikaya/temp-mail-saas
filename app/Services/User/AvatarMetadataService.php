<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Service;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class AvatarMetadataService extends Service
{
    public function metadata(?User $user = null): array
    {
        if ($user === null || blank($user->avatar_disk) || blank($user->avatar_path)) {
            return $this->fallback();
        }

        return [
            'disk' => $user->avatar_disk,
            'path' => $user->avatar_path,
            'mime' => $user->avatar_mime,
            'size' => $user->avatar_size,
            'hash' => $user->avatar_hash,
            'updated_at' => $user->avatar_updated_at?->toISOString(),
            'url' => $this->url($user),
            'fallback' => false,
        ];
    }

    public function url(?User $user = null): string
    {
        if ($user === null || blank($user->avatar_disk) || blank($user->avatar_path)) {
            return $this->defaultUrl();
        }

        try {
            return Storage::disk($user->avatar_disk)->url($user->avatar_path);
        } catch (Throwable) {
            return $this->defaultUrl();
        }
    }

    public function fallback(): array
    {
        return [
            'disk' => null,
            'path' => null,
            'mime' => null,
            'size' => null,
            'hash' => null,
            'updated_at' => null,
            'url' => $this->defaultUrl(),
            'fallback' => true,
        ];
    }

    public function defaultUrl(): string
    {
        return '/images/avatar-default.svg';
    }
}
