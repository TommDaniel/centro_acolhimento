<?php

namespace App\Exceptions;

use RuntimeException;

class MfaCooldownException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('A verificação de segurança está temporariamente indisponível.');
    }
}
