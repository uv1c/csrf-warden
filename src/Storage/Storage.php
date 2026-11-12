<?php

declare(strict_types=1);

namespace CsrfWarden\Storage;

interface Storage
{
    public function get(string $key): ?string;

    public function set(string $key, string $value): void;

    public function remove(string $key): void;
}
