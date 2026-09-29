<?php

namespace App\Enums;

enum AcolhimentoSituacao: string
{
    case NaUnidade = 'na_unidade';
    case Evadido = 'evadido';
    case Internado = 'internado';
    case Desacolhido = 'desacolhido';
}
