<?php

declare(strict_types=1);

namespace CsrfWarden\Http;

final class GlobalsRequest implements RequestContext
{
    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function post(string $name): mixed
    {
        return $_POST[$name] ?? null;
    }

    public function clientIp(): ?string
    {
        $value = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($value) ? $value : null;
    }
}
