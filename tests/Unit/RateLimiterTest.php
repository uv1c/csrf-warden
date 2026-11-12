<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\RateLimit\InMemoryRateLimiter;
use CsrfWarden\RateLimit\StorageRateLimiter;
use CsrfWarden\Storage\ArrayStorage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private const NOW = 1_700_000_000;

    public function testInMemoryAllowsTheLimitThenBlocks(): void
    {
        $limiter = new InMemoryRateLimiter(new TestClock(self::NOW), 2, 60);

        self::assertTrue($limiter->allow('login'));
        self::assertTrue($limiter->allow('login'));
        self::assertFalse($limiter->allow('login'));
    }

    public function testInMemoryResetsInTheNextWindow(): void
    {
        $clock = new TestClock(self::NOW);
        $limiter = new InMemoryRateLimiter($clock, 1, 60);

        self::assertTrue($limiter->allow('login'));
        self::assertFalse($limiter->allow('login'));

        $clock->advance(60);

        self::assertTrue($limiter->allow('login'));
    }

    public function testKeysAreIndependent(): void
    {
        $limiter = new InMemoryRateLimiter(new TestClock(self::NOW), 1, 60);

        self::assertTrue($limiter->allow('login'));
        self::assertFalse($limiter->allow('login'));
        self::assertTrue($limiter->allow('signup'));
    }

    public function testStorageLimiterPersistsAcrossInstances(): void
    {
        $storage = new ArrayStorage();
        $clock = new TestClock(self::NOW);
        $first = new StorageRateLimiter($storage, $clock, 2, 60);

        self::assertTrue($first->allow('login'));
        self::assertTrue($first->allow('login'));

        $second = new StorageRateLimiter($storage, $clock, 2, 60);

        self::assertFalse($second->allow('login'));
    }

    public function testStorageLimiterResetsInTheNextWindow(): void
    {
        $clock = new TestClock(self::NOW);
        $limiter = new StorageRateLimiter(new ArrayStorage(), $clock, 1, 60);

        self::assertTrue($limiter->allow('login'));
        self::assertFalse($limiter->allow('login'));

        $clock->advance(60);

        self::assertTrue($limiter->allow('login'));
    }

    public function testInvalidLimitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InMemoryRateLimiter(new TestClock(self::NOW), 0, 60);
    }

    public function testInvalidWindowIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StorageRateLimiter(new ArrayStorage(), new TestClock(self::NOW), 1, 0);
    }
}
