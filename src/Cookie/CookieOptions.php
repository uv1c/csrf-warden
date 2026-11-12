<?php

declare(strict_types=1);

namespace CsrfWarden\Cookie;

use InvalidArgumentException;

final class CookieOptions
{
    private const SAME_SITE_VALUES = ['Lax', 'Strict', 'None'];

    public function __construct(
        private string $name = '_csrf_binding',
        private string $path = '/',
        private ?string $domain = null,
        private bool $secure = true,
        private bool $httpOnly = true,
        private string $sameSite = 'Lax',
        private int $lifetimeSeconds = 7200
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Cookie name must not be empty.');
        }

        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('Cookie path must start with "/".');
        }

        if (!in_array($sameSite, self::SAME_SITE_VALUES, true)) {
            throw new InvalidArgumentException('Cookie SameSite must be Lax, Strict, or None.');
        }

        if ($sameSite === 'None' && !$secure) {
            throw new InvalidArgumentException('SameSite=None requires Secure.');
        }

        if ($lifetimeSeconds < 1) {
            throw new InvalidArgumentException('Cookie lifetime must be positive.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function domain(): ?string
    {
        return $this->domain;
    }

    public function secure(): bool
    {
        return $this->secure;
    }

    public function httpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function sameSite(): string
    {
        return $this->sameSite;
    }

    public function lifetimeSeconds(): int
    {
        return $this->lifetimeSeconds;
    }
}
