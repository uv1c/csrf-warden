<?php

declare(strict_types=1);

namespace CsrfWarden\Cookie;

interface CookieJar
{
    public function get(string $name): ?string;

    public function set(string $name, string $value, CookieOptions $options): void;

    public function delete(string $name, CookieOptions $options): void;
}
