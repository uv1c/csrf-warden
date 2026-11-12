<?php

declare(strict_types=1);

namespace CsrfWarden\Token;

use CsrfWarden\Exception\InvalidLifetime;

final class TokenLifetime
{
    public const DEFAULT_SECONDS = 7200;

    private const MAX_FUTURE_SKEW_SECONDS = 60;

    private function __construct(private int $seconds)
    {
    }

    public static function seconds(int $seconds): self
    {
        if ($seconds < 0) {
            throw InvalidLifetime::becauseSecondsAreNegative($seconds);
        }

        return new self($seconds);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_SECONDS);
    }

    public static function neverExpires(): self
    {
        return new self(0);
    }

    public function allows(int $ageInSeconds): bool
    {
        if ($ageInSeconds < -self::MAX_FUTURE_SKEW_SECONDS) {
            return false;
        }

        if ($this->seconds === 0) {
            return true;
        }

        return $ageInSeconds <= $this->seconds;
    }

    public function durationInSeconds(): int
    {
        return $this->seconds;
    }
}
