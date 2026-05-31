<?php

namespace App\Services\Mail;

use App\Services\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MimeValidationService extends Service
{
    public function validatePayload(array $payload): bool
    {
        if ($payload === []) {
            throw ValidationException::withMessages([
                'payload' => 'Inbound payload may not be empty.',
            ]);
        }

        foreach (['subject', 'from_email', 'sender', 'From', 'TextBody', 'HtmlBody', 'body-plain', 'body-html'] as $field) {
            if (isset($payload[$field]) && ! is_scalar($payload[$field])) {
                throw ValidationException::withMessages([
                    $field => "Inbound {$field} must be scalar.",
                ]);
            }
        }

        foreach (['from_email', 'sender', 'From'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field]) && str_contains($payload[$field], "\n")) {
                throw ValidationException::withMessages([
                    $field => 'Inbound headers may not contain line breaks.',
                ]);
            }
        }

        $size = strlen(json_encode($payload, JSON_THROW_ON_ERROR));
        $maxKb = max(1, (int) config('inbound.storage.max_payload_kb', 2048));

        if ($size > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'payload' => 'Inbound payload exceeds the configured size limit.',
            ]);
        }

        return true;
    }

    public function normalizeHeaderName(string $header): string
    {
        return Str::of($header)->lower()->replace('_', '-')->toString();
    }
}
