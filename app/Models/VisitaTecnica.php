<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'crianca_id', 'data_visita', 'hora_visita', 'tipo', 'visitante',
    'local', 'motivo', 'relato', 'encaminhamentos', 'setor_id', 'numero_oficio',
])]
class VisitaTecnica extends Model
{
    protected $table = 'visitas_tecnicas';

    protected function casts(): array
    {
        return [
            'data_visita' => 'date',
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

    /**
     * @return array<string, string>
     */
    public function secoes(): array
    {
        return array_filter([
            'Tipo de visita' => $this->tipo,
            'Visitante / responsável' => $this->visitante,
            'Local' => $this->local,
            'Motivo' => $this->motivo,
            'Relato da visita' => $this->relato,
            'Encaminhamentos' => $this->encaminhamentos,
        ], fn (?string $valor) => filled($valor));
    }
}
