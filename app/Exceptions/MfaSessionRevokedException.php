<?php

namespace App\Exceptions;

use RuntimeException;

class MfaSessionRevokedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The restricted authentication session is no longer valid.');
    }
}
