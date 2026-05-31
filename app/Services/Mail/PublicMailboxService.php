<?php

namespace App\Services\Mail;

use App\Models\User;
use App\Services\Billing\FeatureGateService;
use App\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PublicMailboxService extends Service
{
    private const RESERVED_PARTS = [
        'admin',
        'api',
        'billing',
        'help',
        'hostmaster',
        'postmaster',
        'root',
        'security',
        'support',
        'webmaster',
    ];

    public function __construct(
        private readonly FeatureGateService $features,
    ) {}

    public function current(Request $request): ?string
    {
        $address = $request->session()->get($this->sessionKey());

        return is_string($address) ? $this->normalize($address) : null;
    }

    public function generate(Request $request): string
    {
        $address = $this->makeAddress($request->user());
        $request->session()->put($this->sessionKey(), $address);

        return $address;
    }

    public function rotate(Request $request): string
    {
        return $this->generate($request);
    }

    public function forget(Request $request): void
    {
        $request->session()->forget($this->sessionKey());
    }

    public function normalize(string $address): ?string
    {
        $address = Str::lower(trim($address));

        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        [$local, $domain] = explode('@', $address, 2);

        if (! $this->domainAllowed($domain)) {
            return null;
        }

        if (! preg_match('/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/', $local)) {
            return null;
        }

        return $local.'@'.$domain;
    }

    public function allowedDomains(?User $user = null): array
    {
        $domains = config('domains.public_mailbox.allowed_domains', []);
        $domains = is_array($domains) ? $domains : [];
        $domains = array_values(array_unique(array_filter(array_map(
            fn (mixed $domain): string => Str::lower(trim((string) $domain)),
            $domains,
        ))));

        $domains = $domains ?: [$this->defaultDomain()];
        $planDomains = $this->features->featureValue('allowed_domains', $user, $domains);
        $planDomains = is_array($planDomains) ? $planDomains : $domains;
        $allowed = array_values(array_intersect($domains, $planDomains));

        return $allowed ?: $domains;
    }

    public function defaultDomain(): string
    {
        $domain = Str::lower(trim((string) config('domains.public_mailbox.default_domain', 'example.test')));

        return $domain !== '' ? $domain : 'example.test';
    }

    private function makeAddress(?User $user = null): string
    {
        do {
            $local = $this->makeLocalPart();
        } while (in_array($local, self::RESERVED_PARTS, true));

        return $local.'@'.$this->selectDomain($user);
    }

    private function makeLocalPart(): string
    {
        $length = max(8, min(32, (int) config('tempmail.public_inbox.mailbox_local_part_length', 12)));
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $part = '';

        for ($i = 0; $i < $length; $i++) {
            $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $part;
    }

    private function selectDomain(?User $user = null): string
    {
        $domains = $this->allowedDomains($user);

        return $domains[array_rand($domains)];
    }

    private function domainAllowed(string $domain): bool
    {
        $domains = config('domains.public_mailbox.allowed_domains', []);
        $domains = is_array($domains) ? $domains : [];

        return in_array(Str::lower($domain), array_map(
            fn (mixed $allowed): string => Str::lower(trim((string) $allowed)),
            $domains ?: [$this->defaultDomain()],
        ), true);
    }

    private function sessionKey(): string
    {
        return (string) config('tempmail.public_inbox.mailbox_session_key', 'public_inbox.mailbox');
    }
}
