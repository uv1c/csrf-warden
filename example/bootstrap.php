<?php

declare(strict_types=1);

use CsrfWarden\Cookie\CookieOptions;
use CsrfWarden\Cookie\NativeCookieJar;
use CsrfWarden\CSRF;
use CsrfWarden\Http\OriginVerifier;
use CsrfWarden\RateLimit\StorageRateLimiter;
use CsrfWarden\Security\DoubleSubmitCookie;
use CsrfWarden\Security\SecurityPolicy;
use CsrfWarden\Storage\SessionStorage;
use CsrfWarden\SystemClock;
use CsrfWarden\Token\TokenLifetime;

/**
 * Shared bootstrap for the examples.
 *
 * Uses Composer's autoloader when available; otherwise registers a small PSR-4
 * loader for src/ so the examples also work before `composer install`.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'CsrfWarden\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }
    });
}

final class Example
{
    public static function csrf(): CSRF
    {
        self::startSession();
        $clock = new SystemClock();

        return new CSRF(
            new SessionStorage(),
            TokenLifetime::default(),
            $clock,
            new SecurityPolicy(
                new DoubleSubmitCookie(
                    new NativeCookieJar(),
                    new CookieOptions(secure: self::isHttps(), sameSite: 'Lax')
                ),
                new StorageRateLimiter(new SessionStorage('_csrf_warden_rate'), $clock, 120, 60),
                // Demo only: derive from the current host. Production code should
                // list fixed origins from configuration.
                new OriginVerifier([self::origin()])
            )
        );
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::isHttps(),
        ]);

        session_start();
    }

    public static function origin(): string
    {
        $scheme = self::isHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';

        return $scheme . '://' . $host;
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
