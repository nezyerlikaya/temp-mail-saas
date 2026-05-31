<?php

namespace App\Services\Mail;

use App\Services\Service;
use Illuminate\Validation\ValidationException;

final class AttachmentValidationService extends Service
{
    public function validate(array $attachments): bool
    {
        $maxCount = max(0, (int) config('inbound.storage.max_attachment_metadata_count', 25));

        if (count($attachments) > $maxCount) {
            throw ValidationException::withMessages([
                'attachments' => "Attachment metadata count may not exceed {$maxCount}.",
            ]);
        }

        foreach ($attachments as $index => $attachment) {
            if (! is_array($attachment)) {
                throw ValidationException::withMessages([
                    "attachments.{$index}" => 'Attachment metadata must be an object.',
                ]);
            }

            $mime = $attachment['mime_type'] ?? $attachment['content-type'] ?? $attachment['ContentType'] ?? null;

            if ($mime !== null && (! is_string($mime) || ! preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i', $mime))) {
                throw ValidationException::withMessages([
                    "attachments.{$index}.mime_type" => 'Attachment MIME type is invalid.',
                ]);
            }

            $size = $attachment['size'] ?? $attachment['Size'] ?? null;

            if ($size !== null && (! is_numeric($size) || (int) $size < 0 || (int) $size > $this->maxBytes())) {
                throw ValidationException::withMessages([
                    "attachments.{$index}.size" => 'Attachment size is invalid.',
                ]);
            }
        }

        return true;
    }

    public function storageReady(): bool
    {
        return is_dir(storage_path('app')) && is_writable(storage_path('app'));
    }

    private function maxBytes(): int
    {
        return max(1, (int) config('media.limits.attachments.max_size_kb', config('media.attachment_limits.max_size_kb', 10240))) * 1024;
    }
}
