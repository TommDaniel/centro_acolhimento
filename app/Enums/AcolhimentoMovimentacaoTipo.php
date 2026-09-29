<?php

namespace App\Enums;

enum AcolhimentoMovimentacaoTipo: string
{
    case Ingresso = 'ingresso';
    case Evasao = 'evasao';
    case Retorno = 'retorno';
    case Internacao = 'internacao';
    case Desacolhimento = 'desacolhimento';

    public function situacaoResultante(): AcolhimentoSituacao
    {
        return match ($this) {
            self::Ingresso, self::Retorno => AcolhimentoSituacao::NaUnidade,
            self::Evasao => AcolhimentoSituacao::Evadido,
            self::Internacao => AcolhimentoSituacao::Internado,
            self::Desacolhimento => AcolhimentoSituacao::Desacolhido,
        };
    }
}
