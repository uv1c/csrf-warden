<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Http\RequestContext;

final class ArrayRequest implements RequestContext
{
    /**
     * @param array<string,string> $headers
     * @param array<string,mixed> $postValues
     */
    public function __construct(
        private string $method = 'GET',
        private array $headers = [],
        private array $postValues = [],
        private ?string $clientIp = null
    ) {
    }

    public function method(): string
    {
        return strtoupper($this->method);
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function post(string $name): mixed
    {
        return $this->postValues[$name] ?? null;
    }

    public function clientIp(): ?string
    {
        return $this->clientIp;
    }
}
