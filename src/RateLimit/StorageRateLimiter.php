<?php

declare(strict_types=1);

namespace CsrfWarden\RateLimit;

use CsrfWarden\Clock;
use CsrfWarden\Storage\Storage;
use InvalidArgumentException;

/**
 * Fixed-window limiter backed by Storage.
 *
 * The read-modify-write cycle is intentionally simple. For strict limits under
 * heavy concurrency, use a backend with atomic increments (Redis, database).
 */
final class StorageRateLimiter implements RateLimiter
{
    public function __construct(
        private Storage $storage,
        private Clock $clock,
        private int $limit = 120,
        private int $windowSeconds = 60
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException('Rate limit must be at least 1.');
        }

        if ($windowSeconds < 1) {
            throw new InvalidArgumentException('Rate limit window must be at least 1 second.');
        }
    }

    public function allow(string $key): bool
    {
        $storageKey = 'rate:' . hash('sha256', $key);
        $window = intdiv($this->clock->now(), $this->windowSeconds);
        $stored = $this->storage->get($storageKey);
        $state = $stored === null ? null : json_decode($stored, true);

        if (!is_array($state) || ($state['window'] ?? null) !== $window || !isset($state['count'])) {
            $state = ['window' => $window, 'count' => 0];
        }

        if ((int) $state['count'] >= $this->limit) {
            return false;
        }

        $state['count'] = (int) $state['count'] + 1;
        $this->storage->set($storageKey, json_encode($state, JSON_THROW_ON_ERROR));

        return true;
    }
}
