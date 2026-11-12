<?php

declare(strict_types=1);

namespace CsrfWarden\Token;

use CsrfWarden\Clock;
use CsrfWarden\Secret;

final class TokenService
{
    private TokenCodec $codec;

    public function __construct(
        private Secret $secret,
        private TokenLifetime $lifetime,
        private Clock $clock
    ) {
        $this->codec = new TokenCodec();
    }

    public function issue(string $scope = ''): string
    {
        $timestamp = pack('J', $this->clock->now());
        $pad = random_bytes(32);
        $mac = $this->mac($scope, $timestamp);

        return $this->codec->encode($pad, $mac, $timestamp);
    }

    public function isValid(mixed $token, string $scope = ''): bool
    {
        if (!is_string($token)) {
            return false;
        }

        $decoded = $this->codec->decode($token);
        if ($decoded === null) {
            return false;
        }

        $age = $this->clock->now() - $decoded->unixTimestamp();
        if (!$this->lifetime->allows($age)) {
            return false;
        }

        return hash_equals(
            $this->mac($scope, $decoded->timestamp()),
            $decoded->maskedMac() ^ $decoded->pad()
        );
    }

    private function mac(string $scope, string $timestamp): string
    {
        $scopeLength = pack('N', strlen($scope));

        return hash_hmac('sha256', $scopeLength . $scope . $timestamp, $this->secret->value(), true);
    }
}
