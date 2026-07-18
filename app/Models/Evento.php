<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'titulo', 'tipo', 'descricao', 'inicio', 'fim',
    'dia_inteiro', 'concluido', 'crianca_id', 'setor_id',
])]
class Evento extends Model
{
    public const TIPOS = [
        'visita' => 'Visita',
        'audiencia' => 'Audiência / Judiciário',
        'atendimento' => 'Atendimento',
        'tarefa' => 'Tarefa',
        'outro' => 'Outro',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'dia_inteiro' => 'boolean',
            'concluido' => 'boolean',
        ];
    }

    public function crianca(): BelongsTo
    {
        return $this->belongsTo(Crianca::class);
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
