<?php

declare(strict_types=1);

namespace CsrfWarden\Storage;

interface AtomicStorage extends Storage
{
    /**
     * Store the value only when the key does not exist yet.
     *
     * @return bool true when the value was stored, false when the key already existed
     */
    public function add(string $key, string $value): bool;
}
