<?php

namespace App\Services\User;

use App\Services\Service;
use Illuminate\Support\Str;

final class UsernameService extends Service
{
    private const MIN_LENGTH = 3;

    private const MAX_LENGTH = 32;

    private const RESERVED = [
        'admin',
        'administrator',
        'root',
        'support',
        'moderator',
        'api',
        'login',
        'register',
        'mail',
        'inbox',
        'status',
        'health',
        'system',
        'billing',
        'security',
        'abuse',
        'owner',
        'superadmin',
    ];

    public function normalize(string $username): string
    {
        $normalized = Str::lower(trim($username));
        $normalized = preg_replace('/\s+/', '-', $normalized) ?? '';
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized) ?? '';
        $normalized = preg_replace('/-+/', '-', $normalized) ?? '';

        return trim($normalized, '-_');
    }

    public function isValid(string $username): bool
    {
        $normalized = $this->normalize($username);
        $length = strlen($normalized);

        return $length >= self::MIN_LENGTH
            && $length <= self::MAX_LENGTH
            && preg_match('/^[a-z0-9][a-z0-9_-]*[a-z0-9]$/', $normalized) === 1
            && ! $this->isReserved($normalized);
    }

    public function isReserved(string $username): bool
    {
        return in_array($this->normalize($username), self::RESERVED, true);
    }

    public function publicSlugSuggestion(string $username): ?string
    {
        $normalized = $this->normalize($username);

        return $this->isValid($normalized) ? $normalized : null;
    }
}
