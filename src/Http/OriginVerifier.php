<?php

declare(strict_types=1);

namespace CsrfWarden\Http;

use InvalidArgumentException;

/**
 * Prefers Fetch Metadata and Origin over Referer, and fails closed when none
 * of them can establish a same-origin request.
 */
final class OriginVerifier
{
    /** @var list<string> */
    private array $allowedOrigins = [];

    /**
     * @param list<string> $allowedOrigins e.g. ["https://example.com"]
     */
    public function __construct(array $allowedOrigins)
    {
        foreach ($allowedOrigins as $origin) {
            $normalized = $this->normalizeOrigin($origin);
            if ($normalized === null) {
                throw new InvalidArgumentException(sprintf('Invalid allowed origin "%s".', $origin));
            }

            $this->allowedOrigins[] = $normalized;
        }

        $this->allowedOrigins = array_values(array_unique($this->allowedOrigins));
    }

    public function isAllowed(RequestContext $request): bool
    {
        if (!$this->isStateChanging($request->method())) {
            return true;
        }

        $fetchSite = strtolower((string) $request->header('Sec-Fetch-Site'));

        if ($fetchSite === 'cross-site') {
            return false;
        }

        $origin = $request->header('Origin');
        if ($origin !== null && $origin !== '') {
            return $this->matches($origin);
        }

        $referer = $request->header('Referer');
        if ($referer !== null && $referer !== '') {
            return $this->matches($referer);
        }

        return $fetchSite === 'same-origin' || $fetchSite === 'none';
    }

    private function isStateChanging(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function matches(string $url): bool
    {
        $origin = $this->normalizeOrigin($url);

        return $origin !== null && in_array($origin, $this->allowedOrigins, true);
    }

    private function normalizeOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = null;
        }

        return $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
    }
}
