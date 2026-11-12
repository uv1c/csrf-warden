<?php

declare(strict_types=1);

namespace CsrfWarden\Security;

use CsrfWarden\Cookie\CookieJar;
use CsrfWarden\Cookie\CookieOptions;

/**
 * Double-submit cookie binding.
 *
 * The cookie value is a 256-bit random hex string. Every form/AJAX request must
 * echo the same value. An attacker on another origin cannot read the cookie or
 * the rendered hidden field. The CSRF token itself stays unique per form.
 */
final class DoubleSubmitCookie
{
    public const FIELD_NAME = '_csrf_binding';
    public const HEADER_NAME = 'X-CSRF-BINDING';

    private CookieOptions $options;

    public function __construct(
        private CookieJar $cookies,
        ?CookieOptions $options = null
    ) {
        $this->options = $options ?? new CookieOptions();
    }

    public function binding(): string
    {
        $existing = $this->cookies->get($this->options->name());
        if (is_string($existing) && self::isWellFormed($existing)) {
            return $existing;
        }

        $binding = bin2hex(random_bytes(32));
        $this->cookies->set($this->options->name(), $binding, $this->options);

        return $binding;
    }

    public function hasCookie(): bool
    {
        return $this->cookies->get($this->options->name()) !== null;
    }

    public function matches(mixed $submitted): bool
    {
        $cookie = $this->cookies->get($this->options->name());

        return is_string($cookie)
            && is_string($submitted)
            && hash_equals($cookie, $submitted);
    }

    public function rotate(): void
    {
        $this->cookies->delete($this->options->name(), $this->options);
    }

    public function cookieName(): string
    {
        return $this->options->name();
    }

    private static function isWellFormed(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }
}
