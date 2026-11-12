<?php

declare(strict_types=1);

namespace CsrfWarden\Exception;

use LogicException;

final class SessionNotStarted extends LogicException
{
    public static function create(): self
    {
        return new self('Call session_start() before using SessionStorage.');
    }
}
