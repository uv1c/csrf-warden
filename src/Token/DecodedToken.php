<?php

declare(strict_types=1);

namespace CsrfWarden\Token;

final class DecodedToken
{
    public function __construct(
        private string $pad,
        private string $maskedMac,
        private string $timestamp
    ) {
    }

    public function pad(): string
    {
        return $this->pad;
    }

    public function maskedMac(): string
    {
        return $this->maskedMac;
    }

    public function timestamp(): string
    {
        return $this->timestamp;
    }

    public function unixTimestamp(): int
    {
        $unpacked = unpack('J', $this->timestamp);

        return is_array($unpacked) ? (int) $unpacked[1] : 0;
    }
}
