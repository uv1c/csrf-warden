<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Cookie\CookieOptions;
use CsrfWarden\Security\DoubleSubmitCookie;
use PHPUnit\Framework\TestCase;

final class DoubleSubmitCookieTest extends TestCase
{
    public function testBindingIsA256BitHexValue(): void
    {
        $cookies = new ArrayCookieJar();
        $doubleSubmit = new DoubleSubmitCookie($cookies, $this->options());

        $binding = $doubleSubmit->binding();

        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $binding);
        self::assertSame($binding, $cookies->get('_csrf_binding'));
    }

    public function testBindingIsStableWithinTheSameCookie(): void
    {
        $doubleSubmit = new DoubleSubmitCookie(new ArrayCookieJar(), $this->options());

        self::assertSame($doubleSubmit->binding(), $doubleSubmit->binding());
    }

    public function testMatchesUsesTheExactCookieValue(): void
    {
        $cookies = new ArrayCookieJar();
        $doubleSubmit = new DoubleSubmitCookie($cookies, $this->options());
        $binding = $doubleSubmit->binding();

        self::assertTrue($doubleSubmit->matches($binding));
        self::assertFalse($doubleSubmit->matches(str_repeat('a', 64)));
        self::assertFalse($doubleSubmit->matches(null));
        self::assertFalse($doubleSubmit->matches(substr($binding, 0, 63)));
    }

    public function testMalformedCookieIsReplaced(): void
    {
        $cookies = new ArrayCookieJar();
        $cookies->set('_csrf_binding', 'too-short', $this->options());
        $doubleSubmit = new DoubleSubmitCookie($cookies, $this->options());

        $binding = $doubleSubmit->binding();

        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $binding);
        self::assertSame($binding, $cookies->get('_csrf_binding'));
    }

    public function testRotateDeletesTheBinding(): void
    {
        $cookies = new ArrayCookieJar();
        $doubleSubmit = new DoubleSubmitCookie($cookies, $this->options());
        $doubleSubmit->binding();

        $doubleSubmit->rotate();

        self::assertFalse($doubleSubmit->hasCookie());
        self::assertNull($cookies->get('_csrf_binding'));
    }

    private function options(): CookieOptions
    {
        return new CookieOptions('_csrf_binding', '/', null, false, true, 'Lax', 7200);
    }
}
