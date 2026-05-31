<?php

namespace App\Services\Abuse;

use App\Services\Service;
use Illuminate\Http\Request;

final class AbuseSignalService extends Service
{
    public function collect(?Request $request = null): array
    {
        $request ??= request();

        return [
            'ip_hash' => $this->hashIp($request->ip()),
            'session_hash' => $this->hashSession($this->sessionIdentifier($request)),
            'user_agent_hash' => $this->hashUserAgent($request->userAgent()),
            'user_id' => config('abuse.user_signal_enabled', true) ? $request->user()?->getAuthIdentifier() : null,
            'route_name' => $request->route()?->getName(),
            'endpoint' => '/'.ltrim($request->path(), '/'),
            'method' => $request->method(),
        ];
    }

    public function limiterKey(?Request $request = null): string
    {
        $signals = $this->collect($request);

        return implode('|', array_filter([
            $signals['ip_hash'] ?? $signals['session_hash'],
            $signals['user_id'] !== null ? 'user:'.$signals['user_id'] : null,
        ])) ?: 'anonymous';
    }

    public function hashIp(?string $ip): ?string
    {
        if (! config('abuse.ip_hashing_enabled', true)) {
            return null;
        }

        return $this->hashValue($ip);
    }

    public function hashSession(?string $sessionId): ?string
    {
        if (! config('abuse.session_hashing_enabled', true)) {
            return null;
        }

        return $this->hashValue($sessionId);
    }

    public function hashUserAgent(?string $userAgent): ?string
    {
        return $this->hashValue($userAgent);
    }

    private function sessionIdentifier(Request $request): ?string
    {
        return $request->hasSession() ? $request->session()->getId() : null;
    }

    private function hashValue(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('abuse.hash_salt', config('app.key', 'local-abuse-salt')));
    }
}
