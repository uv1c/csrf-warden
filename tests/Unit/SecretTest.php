<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Secret;
use CsrfWarden\Storage\ArrayStorage;
use PHPUnit\Framework\TestCase;

final class SecretTest extends TestCase
{
    public function testSecretIsCreatedOnce(): void
    {
        $storage = new ArrayStorage();
        $secret = new Secret($storage);

        $first = $secret->value();
        $second = $secret->value();

        self::assertSame($first, $second);
        self::assertSame(32, strlen($first));
    }

    public function testCorruptSecretIsReplaced(): void
    {
        $storage = new ArrayStorage();
        $storage->set('secret', 'too-short');
        $secret = new Secret($storage);

        $value = $secret->value();

        self::assertSame(32, strlen($value));
        self::assertSame($value, $storage->get('secret'));
    }

    public function testAtomicRaceUsesTheStoredSecret(): void
    {
        $storage = new RacingStorage();
        $secret = new Secret($storage);

        $value = $secret->value();

        self::assertTrue($storage->addWasCalled);
        self::assertSame($storage->winner(), $value);
    }

    public function testFingerprintIsStableAndChangesAfterRotation(): void
    {
        $secret = new Secret(new ArrayStorage());
        $fingerprint = $secret->fingerprint();

        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $fingerprint);
        self::assertSame($fingerprint, $secret->fingerprint());

        $secret->rotate();

        self::assertNotSame($fingerprint, $secret->fingerprint());
    }
}
