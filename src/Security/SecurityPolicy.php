<?php

declare(strict_types=1);

namespace CsrfWarden\Security;

use CsrfWarden\Http\OriginVerifier;
use CsrfWarden\RateLimit\RateLimiter;

final class SecurityPolicy
{
    public function __construct(
        private ?DoubleSubmitCookie $doubleSubmit = null,
        private ?RateLimiter $rateLimiter = null,
        private ?OriginVerifier $originVerifier = null
    ) {
    }

    public function doubleSubmit(): ?DoubleSubmitCookie
    {
        return $this->doubleSubmit;
    }

    public function rateLimiter(): ?RateLimiter
    {
        return $this->rateLimiter;
    }

    public function originVerifier(): ?OriginVerifier
    {
        return $this->originVerifier;
    }
}
