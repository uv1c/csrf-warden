<?php

declare(strict_types=1);

namespace CsrfWarden\Cookie;

final class NativeCookieJar implements CookieJar
{
    public function get(string $name): ?string
    {
        $value = $_COOKIE[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    public function set(string $name, string $value, CookieOptions $options): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie($name, $value, [
            'expires' => time() + $options->lifetimeSeconds(),
            'path' => $options->path(),
            'domain' => $options->domain() ?? '',
            'secure' => $options->secure(),
            'httponly' => $options->httpOnly(),
            'samesite' => $options->sameSite(),
        ]);

        $_COOKIE[$name] = $value;
    }

    public function delete(string $name, CookieOptions $options): void
    {
        if (!headers_sent()) {
            setcookie($name, '', [
                'expires' => time() - 3600,
                'path' => $options->path(),
                'domain' => $options->domain() ?? '',
                'secure' => $options->secure(),
                'httponly' => $options->httpOnly(),
                'samesite' => $options->sameSite(),
            ]);
        }

        unset($_COOKIE[$name]);
    }
}
