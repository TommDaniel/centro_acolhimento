<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['crianca_id', 'titulo', 'introducao', 'desenvolvimento', 'consideracoes', 'setor_id', 'numero_oficio'])]
class Report extends Model
{
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

    /**
     * @return array<string, string>
     */
    public function secoes(): array
    {
        return array_filter([
            'Introdução ao ocorrido' => $this->introducao,
            'Desenvolvimento' => $this->desenvolvimento,
            'Considerações / solicitações' => $this->consideracoes,
        ], fn (?string $valor) => filled($valor));
    }
}
