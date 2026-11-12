<?php

declare(strict_types=1);

namespace CsrfWarden\Storage;

final class ArrayStorage implements AtomicStorage
{
    /** @var array<string,string> */
    private array $values = [];

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
        if (array_key_exists($key, $this->values)) {
            return false;
        }

        $this->values[$key] = $value;

        return true;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }
}
