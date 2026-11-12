<?php

declare(strict_types=1);

namespace CsrfWarden\RateLimit;

interface RateLimiter
{
    /**
     * Consume one attempt for the key.
     *
     * @return bool true when the attempt is allowed
     */
    public function allow(string $key): bool;
}
