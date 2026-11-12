<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\CSRF;
use CsrfWarden\Exception\InvalidLifetime;
use CsrfWarden\Storage\ArrayStorage;
use CsrfWarden\Token\TokenLifetime;
use PHPUnit\Framework\TestCase;

final class CSRFTest extends TestCase
{
    private const NOW = 1_700_000_000;

    public function testTokenIsScoped(): void
    {
        $csrf = $this->createCSRF();
        $token = $csrf->token('login');

        self::assertTrue($csrf->check($token, 'login'));
        self::assertFalse($csrf->check($token, 'delete-account'));
    }

    public function testTokenCanBeCheckedMoreThanOnce(): void
    {
        $csrf = $this->createCSRF();
        $token = $csrf->token('form');

        self::assertTrue($csrf->check($token, 'form'));
        self::assertTrue($csrf->check($token, 'form'));
    }

    public function testInvalidInputIsRejected(): void
    {
        $csrf = $this->createCSRF();

        foreach ([null, 123, [], '', 'not-a-token'] as $input) {
            self::assertFalse($csrf->check($input));
        }
    }

    public function testTamperedTokenIsRejected(): void
    {
        $csrf = $this->createCSRF();
        $token = $csrf->token('form');

        for ($index = 0; $index < strlen($token); $index++) {
            $replacement = $token[$index] === 'A' ? 'B' : 'A';
            $tampered = substr($token, 0, $index) . $replacement . substr($token, $index + 1);

            if ($index === strlen($token) - 1) {
                continue; // padding bits can decode to the same bytes
            }

            self::assertFalse($csrf->check($tampered, 'form'));
        }
    }

    public function testRotateInvalidatesIssuedTokens(): void
    {
        $csrf = $this->createCSRF();
        $token = $csrf->token('form');

        $csrf->rotate();

        self::assertFalse($csrf->check($token, 'form'));
    }

    public function testFingerprintIsStableAndChangesAfterRotation(): void
    {
        $csrf = $this->createCSRF();
        $fingerprint = $csrf->fingerprint();

        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $fingerprint);
        self::assertSame($fingerprint, $csrf->fingerprint());

        $csrf->rotate();

        self::assertNotSame($fingerprint, $csrf->fingerprint());
    }

    public function testFieldContainsAValidToken(): void
    {
        $csrf = $this->createCSRF();
        $field = $csrf->field('form');

        self::assertSame(1, preg_match('/name="_csrf_token" value="([A-Za-z0-9_-]+)"/', $field, $matches));
        self::assertTrue($csrf->check($matches[1], 'form'));
    }

    public function testMetaTagContainsAValidToken(): void
    {
        $csrf = $this->createCSRF();
        $metaTag = $csrf->metaTag('form');

        self::assertSame(1, preg_match('/content="([A-Za-z0-9_-]+)"/', $metaTag, $matches));
        self::assertTrue($csrf->check($matches[1], 'form'));
    }

    public function testNegativeLifetimeIsRejected(): void
    {
        $this->expectException(InvalidLifetime::class);

        new CSRF(new ArrayStorage(), TokenLifetime::seconds(-1));
    }

    private function createCSRF(): CSRF
    {
        return new CSRF(
            new ArrayStorage(),
            TokenLifetime::seconds(7200),
            new TestClock(self::NOW)
        );
    }
}
