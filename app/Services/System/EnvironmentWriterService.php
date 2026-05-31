<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Facades\File;
use Throwable;

final class EnvironmentWriterService extends Service
{
    public function __construct(
        private readonly ?string $path = null,
    ) {}

    public function write(array $values): array
    {
        try {
            $path = $this->envPath();
            $contents = File::exists($path) ? File::get($path) : '';
            $lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
            $lines = $contents === '' ? [] : preg_split("/\r\n|\n|\r/", $contents);
            $written = [];

            foreach ($values as $key => $value) {
                $key = strtoupper((string) $key);

                if (! preg_match('/^[A-Z0-9_]+$/', $key)) {
                    continue;
                }

                $line = $key.'='.$this->formatValue((string) $value);
                $updated = false;

                foreach ($lines as $index => $existingLine) {
                    if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', (string) $existingLine)) {
                        $lines[$index] = $line;
                        $updated = true;
                        break;
                    }
                }

                if (! $updated) {
                    $lines[] = $line;
                }

                $written[] = $key;
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, implode($lineEnding, $lines).$lineEnding);

            return [
                'ok' => true,
                'written' => $written,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'written' => [],
                'message' => 'Environment file could not be updated.',
            ];
        }
    }

    private function formatValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (! preg_match('/\s|#|=|"|\'/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function envPath(): string
    {
        return $this->path ?? (string) config('installer.env_path', base_path('.env'));
    }
}
