<?php

namespace App\Exceptions;

use RuntimeException;

class InstitutionContextOperationException extends RuntimeException
{
    public static function unexpected(): self
    {
        return new self('Não foi possível concluir a operação de contexto institucional com segurança.');
    }
}
