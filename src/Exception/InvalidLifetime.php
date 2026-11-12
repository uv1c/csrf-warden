<?php

declare(strict_types=1);

namespace CsrfWarden\Exception;

use InvalidArgumentException;

final class InvalidLifetime extends InvalidArgumentException
{
    public static function becauseSecondsAreNegative(int $seconds): self
    {
        return new self(sprintf('Token lifetime must be zero or greater, got %d.', $seconds));
    }
}
