<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Storage\AtomicStorage;

final class RacingStorage implements AtomicStorage
{
    /** @var array<string,string> */
    private array $values = [];

    public bool $addWasCalled = false;

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->values[$key] = $value;
    }

    public function add(string $key, string $value): bool
    {
        $this->addWasCalled = true;
        $this->values[$key] = $this->winner();

        return false;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function winner(): string
    {
        return str_repeat('w', 32);
    }
}
