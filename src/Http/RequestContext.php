<?php

declare(strict_types=1);

namespace CsrfWarden\Http;

interface RequestContext
{
    public function method(): string;

    public function header(string $name): ?string;

    public function post(string $name): mixed;

    public function clientIp(): ?string;
}
