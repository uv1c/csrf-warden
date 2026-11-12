<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Clock;

final class TestClock implements Clock
{
    public function __construct(private int $now)
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function rewind(int $seconds): void
    {
        $this->now -= $seconds;
    }

    public function set(int $now): void
    {
        $this->now = $now;
    }
}
