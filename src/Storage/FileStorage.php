<?php

declare(strict_types=1);

namespace CsrfWarden\Storage;

use RuntimeException;

/**
 * Small file-backed key-value store.
 *
 * Useful for a stable application secret and shared rate limits when session
 * cookies are unavailable. Keep the directory outside the web root.
 */
final class FileStorage implements AtomicStorage
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)
            && !mkdir($this->directory, 0700, true)
            && !is_dir($this->directory)
        ) {
            throw new RuntimeException(sprintf('Unable to create storage directory "%s".', $this->directory));
        }
    }

    public function get(string $key): ?string
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }

        $value = file_get_contents($path);

        return $value === false ? null : $value;
    }

    public function set(string $key, string $value): void
    {
        $path = $this->path($key);

        if (file_put_contents($path, $value, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to write storage file "%s".', $path));
        }

        @chmod($path, 0600);
    }

    public function add(string $key, string $value): bool
    {
        $path = $this->path($key);
        $handle = @fopen($path, 'x');

        if ($handle === false) {
            if (is_file($path)) {
                return false;
            }

            throw new RuntimeException(sprintf('Unable to create storage file "%s".', $path));
        }

        try {
            if (fwrite($handle, $value) !== strlen($value)) {
                throw new RuntimeException(sprintf('Unable to write storage file "%s".', $path));
            }
        } catch (\Throwable $exception) {
            fclose($handle);
            @unlink($path);

            throw $exception;
        }

        fclose($handle);
        @chmod($path, 0600);

        return true;
    }

    public function remove(string $key): void
    {
        $path = $this->path($key);

        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException(sprintf('Unable to remove storage file "%s".', $path));
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . hash('sha256', $key)
            . '.bin';
    }
}
