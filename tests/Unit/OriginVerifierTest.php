<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Http\OriginVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OriginVerifierTest extends TestCase
{
    public function testSameOriginFetchMetadataIsAllowed(): void
    {
        $verifier = new OriginVerifier(['https://example.test']);

        self::assertTrue($verifier->isAllowed(
            new ArrayRequest('POST', ['Sec-Fetch-Site' => 'same-origin'])
        ));
    }

    public function testCrossSiteFetchMetadataIsRejected(): void
    {
        $verifier = new OriginVerifier(['https://example.test']);

        self::assertFalse($verifier->isAllowed(
            new ArrayRequest('POST', ['Sec-Fetch-Site' => 'cross-site'])
        ));
    }

    public function testMatchingOriginIsAllowed(): void
    {
        $verifier = new OriginVerifier(['https://example.test']);

        self::assertTrue($verifier->isAllowed(
            new ArrayRequest('POST', ['Origin' => 'https://example.test'])
        ));
        self::assertFalse($verifier->isAllowed(
            new ArrayRequest('POST', ['Origin' => 'https://evil.test'])
        ));
    }

    public function testRefererIsUsedOnlyAsAFallback(): void
    {
        $verifier = new OriginVerifier(['https://example.test']);

        self::assertTrue($verifier->isAllowed(
            new ArrayRequest('POST', ['Referer' => 'https://example.test/form'])
        ));
        self::assertFalse($verifier->isAllowed(
            new ArrayRequest('POST', ['Referer' => 'https://evil.test/form'])
        ));
    }

    public function testMissingOriginDataIsRejected(): void
    {
        $verifier = new OriginVerifier(['https://example.test']);

        self::assertFalse($verifier->isAllowed(new ArrayRequest('POST')));
    }

    public function testSafeMethodsDoNotNeedOriginData(): void
    {
        $verifier = new OriginVerifier(['https://example.test']);

        self::assertTrue($verifier->isAllowed(new ArrayRequest('GET')));
        self::assertTrue($verifier->isAllowed(new ArrayRequest('HEAD')));
    }

    public function testDefaultPortsAreNormalized(): void
    {
        $verifier = new OriginVerifier(['https://example.test']);

        self::assertTrue($verifier->isAllowed(
            new ArrayRequest('POST', ['Origin' => 'https://example.test:443'])
        ));
    }

    public function testInvalidAllowedOriginIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OriginVerifier(['not-a-url']);
    }
}
