<?php

declare(strict_types=1);

namespace CsrfWarden;

use CsrfWarden\Storage\AtomicStorage;
use CsrfWarden\Storage\Storage;

/**
 * Owns the per-session secret used to sign tokens.
 *
 * The secret is created once and kept in storage. Implementing AtomicStorage
 * avoids a first-request race where two requests create different secrets.
 */
final class Secret
{
    private const STORAGE_KEY = 'secret';
    private const BYTE_LENGTH = 32;
    private const FINGERPRINT_CONTEXT = 'csrf-warden:fingerprint';

    public function __construct(private Storage $storage)
    {
    }

    public function value(): string
    {
        $stored = $this->storage->get(self::STORAGE_KEY);
        if ($stored !== null && self::isValid($stored)) {
            return $stored;
        }

        $candidate = random_bytes(self::BYTE_LENGTH);
        if ($this->storage instanceof AtomicStorage && $this->storage->add(self::STORAGE_KEY, $candidate)) {
            return $candidate;
        }

        return $this->replaceInvalidSecret($candidate);
    }

    public function fingerprint(): string
    {
        return substr(hash_hmac('sha256', self::FINGERPRINT_CONTEXT, $this->value()), 0, 32);
    }

    public function rotate(): void
    {
        $this->storage->remove(self::STORAGE_KEY);
    }

    private function replaceInvalidSecret(string $candidate): string
    {
        $stored = $this->storage->get(self::STORAGE_KEY);
        if ($stored !== null && self::isValid($stored)) {
            return $stored;
        }

        $this->storage->set(self::STORAGE_KEY, $candidate);

        return $candidate;
    }

    private static function isValid(string $secret): bool
    {
        return strlen($secret) === self::BYTE_LENGTH;
    }
}
