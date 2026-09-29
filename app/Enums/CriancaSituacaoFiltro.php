<?php

namespace App\Enums;

enum CriancaSituacaoFiltro: string
{
    case Todos = 'todos';
    case Acolhidos = 'acolhidos';
    case Desacolhidos = 'desacolhidos';
    case Evadidos = 'evadidos';
    case Internados = 'internados';
    case AConferir = 'a_conferir';
    case SemIngresso = 'sem_ingresso';

    public function label(): string
    {
        return match ($this) {
            self::Todos => 'Todos',
            self::Acolhidos => 'Acolhidos',
            self::Desacolhidos => 'Desacolhidos',
            self::Evadidos => 'Evadidos',
            self::Internados => 'Internados',
            self::AConferir => 'A conferir',
            self::SemIngresso => 'Sem ingresso',
        };
    }
}
