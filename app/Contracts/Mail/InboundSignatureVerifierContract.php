<?php

namespace App\Contracts\Mail;

interface InboundSignatureVerifierContract
{
    public function verify(array $headers, string|array|null $payload): bool;
}
