<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Cookie\CookieJar;
use CsrfWarden\Cookie\CookieOptions;

final class ArrayCookieJar implements CookieJar
{
    /** @var array<string,string> */
    private array $values = [];

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function set(string $name, string $value, CookieOptions $options): void
    {
        $this->values[$name] = $value;
    }

    public function delete(string $name, CookieOptions $options): void
    {
        unset($this->values[$name]);
    }

    public function clear(): void
    {
        $this->values = [];
    }
}
