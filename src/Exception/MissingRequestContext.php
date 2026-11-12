<?php

declare(strict_types=1);

namespace CsrfWarden\Exception;

use LogicException;

final class MissingRequestContext extends LogicException
{
    public static function forOriginVerifier(): self
    {
        return new self(
            'OriginVerifier requires a RequestContext. Use checkRequest() or pass a RequestContext to check().'
        );
    }
}
