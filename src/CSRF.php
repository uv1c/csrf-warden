<?php

declare(strict_types=1);

namespace CsrfWarden;

use CsrfWarden\Exception\MissingRequestContext;
use CsrfWarden\Http\RequestContext;
use CsrfWarden\Security\DoubleSubmitCookie;
use CsrfWarden\Security\SecurityPolicy;
use CsrfWarden\Storage\Storage;
use CsrfWarden\Token\TokenLifetime;
use CsrfWarden\Token\TokenService;

/**
 * Public facade for issuing, checking, and rotating CSRF tokens.
 *
 * A token is scoped, expires after the configured lifetime, and is masked with
 * fresh random bytes every time it is issued. The per-session secret never
 * leaves the server.
 */
final class CSRF
{
    public const FIELD_NAME = '_csrf_token';
    public const HEADER_NAME = 'X-CSRF-TOKEN';
    public const BINDING_FIELD_NAME = DoubleSubmitCookie::FIELD_NAME;
    public const BINDING_HEADER_NAME = DoubleSubmitCookie::HEADER_NAME;

    private Secret $secret;
    private TokenService $tokens;

    public function __construct(
        Storage $storage,
        ?TokenLifetime $lifetime = null,
        ?Clock $clock = null,
        private ?SecurityPolicy $security = null
    ) {
        $this->secret = new Secret($storage);
        $this->tokens = new TokenService(
            $this->secret,
            $lifetime ?? TokenLifetime::default(),
            $clock ?? new SystemClock()
        );
    }

    /** Issue a token for the given scope (for example a form name). */
    public function token(string $scope = ''): string
    {
        $this->binding();

        return $this->tokens->issue($scope);
    }

    /**
     * Check a token received from the client. Does not consume it.
     *
     * When a SecurityPolicy is configured, rate limits, origin checks, and
     * double-submit binding are applied before the signed token is validated.
     */
    public function check(
        mixed $token,
        string $scope = '',
        mixed $binding = null,
        ?RequestContext $request = null
    ): bool {
        if ($this->security !== null) {
            $originVerifier = $this->security->originVerifier();
            if ($originVerifier !== null) {
                if ($request === null) {
                    throw MissingRequestContext::forOriginVerifier();
                }

                if (!$originVerifier->isAllowed($request)) {
                    return false;
                }
            }

            $rateLimiter = $this->security->rateLimiter();
            if ($rateLimiter !== null && !$rateLimiter->allow($this->rateLimitKey($scope, $request))) {
                return false;
            }

            $doubleSubmit = $this->security->doubleSubmit();
            if ($doubleSubmit !== null
                && $doubleSubmit->hasCookie()
                && !$doubleSubmit->matches($binding)
            ) {
                return false;
            }
        }

        return $this->tokens->isValid($token, $scope);
    }

    /**
     * Extract the token and binding from a request (form field or AJAX header)
     * and validate them.
     */
    public function checkRequest(RequestContext $request, string $scope = ''): bool
    {
        $token = $request->post(self::FIELD_NAME);
        if (!is_string($token)) {
            $token = $request->header(self::HEADER_NAME);
        }

        $binding = $request->post(self::BINDING_FIELD_NAME);
        if (!is_string($binding)) {
            $binding = $request->header(self::BINDING_HEADER_NAME);
        }

        return $this->check($token, $scope, $binding, $request);
    }

    /**
     * Stable, non-secret identifier for this session's CSRF secret.
     *
     * Safe to log or use as a cache key. It changes after rotate() and cannot
     * be used as a CSRF token.
     */
    public function fingerprint(): string
    {
        return $this->secret->fingerprint();
    }

    /** Invalidate every token and double-submit binding issued so far. */
    public function rotate(): void
    {
        $this->secret->rotate();
        $this->security?->doubleSubmit()?->rotate();
    }

    /** Ready-to-echo hidden inputs for a form. */
    public function field(string $scope = ''): string
    {
        $html = '<input type="hidden" name="' . self::FIELD_NAME . '" value="'
            . $this->escape($this->token($scope)) . '">';

        $binding = $this->binding();
        if ($binding !== null) {
            $html .= '<input type="hidden" name="' . self::BINDING_FIELD_NAME . '" value="'
                . $this->escape($binding) . '">';
        }

        return $html;
    }

    /** Ready-to-echo meta tags for AJAX clients. */
    public function metaTag(string $scope = ''): string
    {
        $html = '<meta name="csrf-token" content="'
            . $this->escape($this->token($scope)) . '">';

        $binding = $this->binding();
        if ($binding !== null) {
            $html .= '<meta name="csrf-binding" content="' . $this->escape($binding) . '">';
        }

        return $html;
    }

    private function binding(): ?string
    {
        return $this->security?->doubleSubmit()?->binding();
    }

    private function rateLimitKey(string $scope, ?RequestContext $request): string
    {
        // Without a request context, fall back to the session fingerprint so
        // callers do not all share one global bucket.
        $client = $request?->clientIp() ?? $this->fingerprint();

        return hash('sha256', $scope . '|' . $client);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
