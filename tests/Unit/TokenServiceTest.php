<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Exception\InvalidLifetime;
use CsrfWarden\Secret;
use CsrfWarden\Storage\ArrayStorage;
use CsrfWarden\Storage\Storage;
use CsrfWarden\Token\TokenLifetime;
use CsrfWarden\Token\TokenService;
use PHPUnit\Framework\TestCase;

final class TokenServiceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    public function testTokenExpiresAtTheLifetimeBoundary(): void
    {
        $clock = new TestClock(self::NOW);
        $tokens = $this->createService($clock, 100);
        $token = $tokens->issue('form');

        $clock->advance(100);
        self::assertTrue($tokens->isValid($token, 'form'));

        $clock->advance(1);
        self::assertFalse($tokens->isValid($token, 'form'));
    }

    public function testFutureClockSkewIsLimited(): void
    {
        $clock = new TestClock(self::NOW);
        $tokens = $this->createService($clock);
        $token = $tokens->issue('form');

        $clock->rewind(60);
        self::assertTrue($tokens->isValid($token, 'form'));

        $clock->rewind(1);
        self::assertFalse($tokens->isValid($token, 'form'));
    }

    public function testLifetimeOfZeroNeverExpires(): void
    {
        $clock = new TestClock(self::NOW);
        $tokens = $this->createService($clock, 0);
        $token = $tokens->issue('form');

        $clock->advance(10 * 365 * 86400);

        self::assertTrue($tokens->isValid($token, 'form'));
    }

    public function testTokenFromAnotherSecretIsRejected(): void
    {
        $first = $this->createService(new TestClock(self::NOW));
        $second = $this->createService(new TestClock(self::NOW));

        self::assertFalse($second->isValid($first->issue('form'), 'form'));
    }

    public function testNonAtomicStorageIsSupported(): void
    {
        $storage = new class implements Storage {
            /** @var array<string,string> */
            private array $values = [];

            public function get(string $key): ?string
            {
                return $this->values[$key] ?? null;
            }

            public function set(string $key, string $value): void
            {
                $this->values[$key] = $value;
            }

            public function remove(string $key): void
            {
                unset($this->values[$key]);
            }
        };

        $tokens = new TokenService(
            new Secret($storage),
            TokenLifetime::default(),
            new TestClock(self::NOW)
        );

        self::assertTrue($tokens->isValid($tokens->issue('form'), 'form'));
    }

    public function testNegativeLifetimeIsRejected(): void
    {
        $this->expectException(InvalidLifetime::class);

        TokenLifetime::seconds(-1);
    }

    private function createService(TestClock $clock, int $lifetime = 7200): TokenService
    {
        return new TokenService(
            new Secret(new ArrayStorage()),
            TokenLifetime::seconds($lifetime),
            $clock
        );
    }
}
