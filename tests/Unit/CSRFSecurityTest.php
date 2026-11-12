<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Cookie\CookieOptions;
use CsrfWarden\CSRF;
use CsrfWarden\Exception\MissingRequestContext;
use CsrfWarden\Http\OriginVerifier;
use CsrfWarden\RateLimit\InMemoryRateLimiter;
use CsrfWarden\Security\DoubleSubmitCookie;
use CsrfWarden\Security\SecurityPolicy;
use CsrfWarden\Storage\ArrayStorage;
use CsrfWarden\Token\TokenLifetime;
use PHPUnit\Framework\TestCase;

final class CSRFSecurityTest extends TestCase
{
    private const NOW = 1_700_000_000;

    public function testFieldIncludesTheDoubleSubmitBinding(): void
    {
        $cookies = new ArrayCookieJar();
        $csrf = $this->createCSRF(new SecurityPolicy(
            new DoubleSubmitCookie($cookies, $this->options())
        ));

        $field = $csrf->field('form');

        self::assertStringContainsString('name="_csrf_binding"', $field);
        self::assertNotNull($cookies->get('_csrf_binding'));
    }

    public function testCheckRequestAcceptsMatchingBinding(): void
    {
        $cookies = new ArrayCookieJar();
        $csrf = $this->createCSRF(new SecurityPolicy(
            new DoubleSubmitCookie($cookies, $this->options())
        ));
        [$token, $binding] = $this->fieldValues($csrf->field('form'));

        $request = new ArrayRequest(
            'POST',
            [],
            [CSRF::FIELD_NAME => $token, CSRF::BINDING_FIELD_NAME => $binding],
            '127.0.0.1'
        );

        self::assertTrue($csrf->checkRequest($request, 'form'));
    }

    public function testCheckRequestRejectsWrongBinding(): void
    {
        $cookies = new ArrayCookieJar();
        $csrf = $this->createCSRF(new SecurityPolicy(
            new DoubleSubmitCookie($cookies, $this->options())
        ));
        [$token] = $this->fieldValues($csrf->field('form'));

        $request = new ArrayRequest(
            'POST',
            [],
            [CSRF::FIELD_NAME => $token, CSRF::BINDING_FIELD_NAME => str_repeat('b', 64)],
            '127.0.0.1'
        );

        self::assertFalse($csrf->checkRequest($request, 'form'));
    }

    public function testCookieLessFallbackUsesTheSignedToken(): void
    {
        $cookies = new ArrayCookieJar();
        $csrf = $this->createCSRF(new SecurityPolicy(
            new DoubleSubmitCookie($cookies, $this->options())
        ));
        [$token] = $this->fieldValues($csrf->field('form'));

        $cookies->clear();

        $request = new ArrayRequest(
            'POST',
            [],
            [CSRF::FIELD_NAME => $token],
            '127.0.0.1'
        );

        self::assertTrue($csrf->checkRequest($request, 'form'));
    }

    public function testAjaxHeadersAreChecked(): void
    {
        $cookies = new ArrayCookieJar();
        $csrf = $this->createCSRF(new SecurityPolicy(
            new DoubleSubmitCookie($cookies, $this->options())
        ));
        [$token, $binding] = $this->metaValues($csrf->metaTag('ajax'));

        $request = new ArrayRequest(
            'POST',
            [CSRF::HEADER_NAME => $token, CSRF::BINDING_HEADER_NAME => $binding],
            [],
            '127.0.0.1'
        );

        self::assertTrue($csrf->checkRequest($request, 'ajax'));
    }

    public function testRateLimitBlocksRepeatedChecks(): void
    {
        $csrf = $this->createCSRF(new SecurityPolicy(
            null,
            new InMemoryRateLimiter(new TestClock(self::NOW), 1, 60)
        ));
        $token = $csrf->token('form');

        self::assertTrue($csrf->check($token, 'form'));
        self::assertFalse($csrf->check($token, 'form'));
    }

    public function testRateLimitFallsBackToTheSessionFingerprint(): void
    {
        $clock = new TestClock(self::NOW);
        $first = $this->createCSRF(new SecurityPolicy(
            null,
            new InMemoryRateLimiter($clock, 1, 60)
        ));
        $second = $this->createCSRF(new SecurityPolicy(
            null,
            new InMemoryRateLimiter($clock, 1, 60)
        ));

        self::assertTrue($first->check($first->token('form'), 'form'));
        self::assertFalse($first->check($first->token('form'), 'form'));
        self::assertTrue($second->check($second->token('form'), 'form'));
    }

    public function testCrossSiteRequestIsRejectedEvenWithAValidToken(): void
    {
        $cookies = new ArrayCookieJar();
        $csrf = $this->createCSRF(new SecurityPolicy(
            new DoubleSubmitCookie($cookies, $this->options()),
            null,
            new OriginVerifier(['https://example.test'])
        ));
        [$token, $binding] = $this->fieldValues($csrf->field('form'));

        $sameOrigin = new ArrayRequest(
            'POST',
            ['Origin' => 'https://example.test'],
            [CSRF::FIELD_NAME => $token, CSRF::BINDING_FIELD_NAME => $binding],
            '127.0.0.1'
        );
        $crossSite = new ArrayRequest(
            'POST',
            ['Sec-Fetch-Site' => 'cross-site'],
            [CSRF::FIELD_NAME => $token, CSRF::BINDING_FIELD_NAME => $binding],
            '127.0.0.1'
        );

        self::assertTrue($csrf->checkRequest($sameOrigin, 'form'));
        self::assertFalse($csrf->checkRequest($crossSite, 'form'));
    }

    public function testOriginVerifierRequiresARequestContext(): void
    {
        $this->expectException(MissingRequestContext::class);

        $csrf = $this->createCSRF(new SecurityPolicy(
            null,
            null,
            new OriginVerifier(['https://example.test'])
        ));

        $csrf->check($csrf->token('form'), 'form');
    }

    public function testTokensRemainUniquePerForm(): void
    {
        $cookies = new ArrayCookieJar();
        $csrf = $this->createCSRF(new SecurityPolicy(
            new DoubleSubmitCookie($cookies, $this->options())
        ));

        [$first] = $this->fieldValues($csrf->field('form'));
        [$second] = $this->fieldValues($csrf->field('form'));

        self::assertNotSame($first, $second);
    }

    private function createCSRF(?SecurityPolicy $security = null): CSRF
    {
        return new CSRF(
            new ArrayStorage(),
            TokenLifetime::default(),
            new TestClock(self::NOW),
            $security
        );
    }

    private function options(): CookieOptions
    {
        return new CookieOptions('_csrf_binding', '/', null, false, true, 'Lax', 7200);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function fieldValues(string $html): array
    {
        self::assertSame(1, preg_match('/name="_csrf_token" value="([A-Za-z0-9_-]+)"/', $html, $tokenMatch));
        self::assertSame(1, preg_match('/name="_csrf_binding" value="([a-f0-9]{64})"/', $html, $bindingMatch));

        return [$tokenMatch[1], $bindingMatch[1]];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function metaValues(string $html): array
    {
        self::assertSame(1, preg_match('/name="csrf-token" content="([A-Za-z0-9_-]+)"/', $html, $tokenMatch));
        self::assertSame(1, preg_match('/name="csrf-binding" content="([a-f0-9]{64})"/', $html, $bindingMatch));

        return [$tokenMatch[1], $bindingMatch[1]];
    }
}
