<?php

namespace App\Enums;

enum UserStatus: string
{
    case Ativa = 'ativa';
    case Inativa = 'inativa';
    case PendenteMfa = 'pendente_mfa';
}
