<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Token\TokenLifetime;
use PHPUnit\Framework\TestCase;

final class TokenLifetimeTest extends TestCase
{
    public function testDefaultIsTwoHours(): void
    {
        self::assertSame(7200, TokenLifetime::default()->durationInSeconds());
    }

    public function testNeverExpiresAllowsAnyAge(): void
    {
        self::assertTrue(TokenLifetime::neverExpires()->allows(10 * 365 * 86400));
    }

    public function testBoundariesAreInclusive(): void
    {
        $lifetime = TokenLifetime::seconds(100);

        self::assertTrue($lifetime->allows(-60));
        self::assertFalse($lifetime->allows(-61));
        self::assertTrue($lifetime->allows(0));
        self::assertTrue($lifetime->allows(100));
        self::assertFalse($lifetime->allows(101));
    }
}
