<?php

namespace App\Services\Mail\Providers\Concerns;

trait NormalizesProviderPayloads
{
    private function recipientsFrom(mixed $value, ?string $fallback = null): array
    {
        if (is_string($value)) {
            $items = array_filter(array_map('trim', preg_split('/[,;]/', $value) ?: []));

            return array_map(fn (string $email): array => ['type' => 'to', 'email' => $email], $items);
        }

        if (is_array($value)) {
            return collect($value)
                ->map(function (mixed $recipient): ?array {
                    if (is_string($recipient)) {
                        return ['type' => 'to', 'email' => $recipient];
                    }

                    if (! is_array($recipient)) {
                        return null;
                    }

                    return [
                        'type' => $recipient['type'] ?? 'to',
                        'email' => $recipient['email'] ?? $recipient['Email'] ?? null,
                        'name' => $recipient['name'] ?? $recipient['Name'] ?? null,
                    ];
                })
                ->filter(fn (?array $recipient): bool => filled($recipient['email'] ?? null))
                ->values()
                ->all();
        }

        return filled($fallback) ? [['type' => 'to', 'email' => $fallback]] : [];
    }

    private function safeAttachments(mixed $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        return collect($attachments)
            ->map(fn (mixed $attachment): ?array => is_array($attachment) ? [
                'original_filename' => $attachment['name'] ?? $attachment['Name'] ?? $attachment['filename'] ?? null,
                'mime_type' => $attachment['content-type'] ?? $attachment['ContentType'] ?? $attachment['mime_type'] ?? null,
                'size' => is_numeric($attachment['size'] ?? $attachment['Size'] ?? null) ? (int) ($attachment['size'] ?? $attachment['Size']) : null,
                'checksum' => $attachment['checksum'] ?? null,
            ] : null)
            ->filter()
            ->values()
            ->all();
    }
}
