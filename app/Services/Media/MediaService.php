<?php

namespace App\Services\Media;

use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Models\Media;
use App\Services\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MediaService extends Service
{
    public function create(array $metadata): Media
    {
        $this->validateMetadata($metadata);

        $directory = $this->generateDirectory((string) ($metadata['collection'] ?? 'system'));
        $uuid = $this->generateUuid();
        $extension = $this->normalizeExtension($metadata['extension'] ?? pathinfo((string) $metadata['original_filename'], PATHINFO_EXTENSION));
        $filename = $metadata['filename'] ?? $this->generateFilename($uuid, $extension);
        $visibility = $this->determineVisibility($metadata['collection'] ?? null, $metadata['visibility'] ?? null);
        $storagePath = $directory.'/'.$filename;

        return Media::query()->create([
            'uuid' => $uuid,
            'disk' => $metadata['disk'] ?? config('media.default_disk', 'local'),
            'directory' => $directory,
            'filename' => $filename,
            'original_filename' => $metadata['original_filename'],
            'extension' => $extension,
            'mime_type' => $metadata['mime_type'],
            'size' => (int) $metadata['size'],
            'checksum' => $metadata['checksum'] ?? null,
            'visibility' => $visibility,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
            'uploaded_by_user_id' => $metadata['uploaded_by_user_id'] ?? null,
            'uploaded_by_staff_id' => $metadata['uploaded_by_staff_id'] ?? null,
            'status' => $metadata['status'] ?? MediaStatus::Pending,
            'storage_driver' => $metadata['storage_driver'] ?? config('media.storage_driver', 'local'),
            'storage_path' => $metadata['storage_path'] ?? $storagePath,
        ]);
    }

    public function generateUuid(): string
    {
        return (string) Str::uuid();
    }

    public function generateDirectory(string $collection, ?int $timestamp = null): string
    {
        $base = config("media.paths.{$collection}", $collection);
        $time = $timestamp !== null ? now()->setTimestamp($timestamp) : now();

        return trim($base, '/').'/'.$time->format('Y/m');
    }

    public function determineVisibility(?string $collection = null, MediaVisibility|string|null $visibility = null): MediaVisibility
    {
        if ($visibility instanceof MediaVisibility) {
            return $visibility;
        }

        if (is_string($visibility) && MediaVisibility::tryFrom($visibility) !== null) {
            return MediaVisibility::from($visibility);
        }

        if ($collection !== null && in_array($collection, config('media.visibility.public_directories', []), true)) {
            return MediaVisibility::Public;
        }

        return MediaVisibility::from((string) config('media.visibility.default', MediaVisibility::Private->value));
    }

    public function validateMetadata(array $metadata): void
    {
        $errors = [];

        foreach (['original_filename', 'mime_type', 'size'] as $field) {
            if (blank($metadata[$field] ?? null)) {
                $errors[$field][] = "The {$field} field is required.";
            }
        }

        if (isset($metadata['size']) && (! is_numeric($metadata['size']) || (int) $metadata['size'] < 0)) {
            $errors['size'][] = 'The size must be a non-negative integer.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function generateFilename(string $uuid, ?string $extension): string
    {
        return $extension !== null && $extension !== ''
            ? "{$uuid}.{$extension}"
            : $uuid;
    }

    private function normalizeExtension(?string $extension): ?string
    {
        $extension = Str::lower(trim((string) $extension, " .\t\n\r\0\x0B"));

        return $extension === '' ? null : $extension;
    }
}
