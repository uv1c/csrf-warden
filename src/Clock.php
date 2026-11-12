<?php

declare(strict_types=1);

namespace CsrfWarden;

interface Clock
{
    public function now(): int;
}
