<?php

declare(strict_types=1);

namespace CsrfWarden\RateLimit;

use CsrfWarden\Clock;
use InvalidArgumentException;

/**
 * Fixed-window limiter for a single PHP process. Use StorageRateLimiter with a
 * shared backend for multi-process or cookie-less deployments.
 */
final class InMemoryRateLimiter implements RateLimiter
{
    /** @var array<string,array{window:int,count:int}> */
    private array $buckets = [];

    public function __construct(
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
        $window = intdiv($this->clock->now(), $this->windowSeconds);
        $bucket = $this->buckets[$key] ?? null;

        if (!is_array($bucket) || $bucket['window'] !== $window) {
            $bucket = ['window' => $window, 'count' => 0];
        }

        if ($bucket['count'] >= $this->limit) {
            $this->buckets[$key] = $bucket;

            return false;
        }

        $bucket['count']++;
        $this->buckets[$key] = $bucket;
        $this->prune($window);

        return true;
    }

    private function prune(int $currentWindow): void
    {
        if (count($this->buckets) < 1024) {
            return;
        }

        foreach ($this->buckets as $key => $bucket) {
            if ($bucket['window'] < $currentWindow - 1) {
                unset($this->buckets[$key]);
            }
        }
    }
}
