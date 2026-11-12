<?php

declare(strict_types=1);

namespace CsrfWarden\Token;

/**
 * Wire format: base64url(pad[32] | (mac XOR pad)[32] | timestamp[8, big-endian]).
 *
 * Masking every token with a fresh random pad means the same token never appears
 * twice in a page, which mitigates BREACH-style compression attacks.
 */
final class TokenCodec
{
    private const PAD_LENGTH = 32;
    private const MAC_LENGTH = 32;
    private const TIMESTAMP_LENGTH = 8;
    private const RAW_LENGTH = self::PAD_LENGTH + self::MAC_LENGTH + self::TIMESTAMP_LENGTH;
    private const MAX_TOKEN_LENGTH = 200;

    public function encode(string $pad, string $mac, string $timestamp): string
    {
        $maskedMac = $mac ^ $pad;

        return $this->base64UrlEncode($pad . $maskedMac . $timestamp);
    }

    public function decode(string $token): ?DecodedToken
    {
        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        $raw = $this->base64UrlDecode($token);
        if ($raw === null || strlen($raw) !== self::RAW_LENGTH) {
            return null;
        }

        return new DecodedToken(
            substr($raw, 0, self::PAD_LENGTH),
            substr($raw, self::PAD_LENGTH, self::MAC_LENGTH),
            substr($raw, self::PAD_LENGTH + self::MAC_LENGTH, self::TIMESTAMP_LENGTH)
        );
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $encoded) !== 1) {
            return null;
        }

        $binary = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $binary === false ? null : $binary;
    }
}
