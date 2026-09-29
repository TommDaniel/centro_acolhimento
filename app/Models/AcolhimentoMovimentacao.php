<?php

namespace App\Models;

use App\Enums\AcolhimentoMovimentacaoTipo;
use App\Enums\AcolhimentoSituacao;
use Database\Factories\AcolhimentoMovimentacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tipo', 'situacao_resultante', 'efetiva_em', 'motivo', 'fundamento',
    'local_destino', 'observacao', 'idempotency_key',
])]
class AcolhimentoMovimentacao extends Model
{
    /** @use HasFactory<AcolhimentoMovimentacaoFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'acolhimento_movimentacoes';

    protected function casts(): array
    {
        return [
            'tipo' => AcolhimentoMovimentacaoTipo::class,
            'situacao_resultante' => AcolhimentoSituacao::class,
            'efetiva_em' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function acolhimento(): BelongsTo
    {
        return $this->belongsTo(Acolhimento::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
