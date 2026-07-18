<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'crianca_id', 'itens', 'data_entrega', 'assinatura_entrega',
    'devolvido', 'data_devolucao', 'assinatura_devolucao',
    'observacao_devolucao', 'setor_id', 'numero_oficio',
])]
class Pertence extends Model
{
    protected function casts(): array
    {
        return [
            'itens' => 'array',
            'data_entrega' => 'date',
            'data_devolucao' => 'date',
            'devolvido' => 'boolean',
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
