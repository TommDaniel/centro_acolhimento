<?php

namespace App\Models;

use App\Enums\AcolhimentoSituacao;
use Database\Factories\AcolhimentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'crianca_id', 'ingresso_em', 'motivo', 'fundamento', 'origem_codigo',
    'origem_complemento', 'orgao_condutor_codigo', 'orgao_condutor_complemento',
    'pessoa_condutora', 'idempotency_key',
])]
class Acolhimento extends Model
{
    /** @use HasFactory<AcolhimentoFactory> */
    use HasFactory;

    public const ORIGENS = [
        'poder_judiciario' => 'Poder Judiciário',
        'conselho_tutelar' => 'Conselho Tutelar',
        'ministerio_publico' => 'Ministério Público',
        'defensoria_publica' => 'Defensoria Pública',
        'rede_socioassistencial' => 'Rede socioassistencial',
        'rede_saude' => 'Rede de saúde',
        'familia_responsavel' => 'Família ou responsável',
        'outro' => 'Outro',
    ];

    public const ORGAOS_CONDUTORES = [
        'conselho_tutelar' => 'Conselho Tutelar',
        'poder_judiciario' => 'Poder Judiciário',
        'seguranca_publica' => 'Segurança pública',
        'rede_socioassistencial' => 'Rede socioassistencial',
        'rede_saude' => 'Rede de saúde',
        'outro' => 'Outro',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ingresso_em' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
            'encerrado_em' => 'immutable_datetime',
        ];
    }

    public function crianca(): BelongsTo
    {
        return $this->belongsTo(Crianca::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(AcolhimentoMovimentacao::class)->orderBy('efetiva_em')->orderBy('id');
    }

    public function ultimaMovimentacao(): HasOne
    {
        return $this->hasOne(AcolhimentoMovimentacao::class)->ofMany([
            'efetiva_em' => 'max',
            'id' => 'max',
        ]);
    }

    public function encerramento(): BelongsTo
    {
        return $this->belongsTo(AcolhimentoMovimentacao::class, 'encerrado_por_movimentacao_id');
    }

    public function pias(): HasMany
    {
        return $this->hasMany(Pia::class);
    }

    public function situacaoAtual(): AcolhimentoSituacao
    {
        return $this->ultimaMovimentacao?->situacao_resultante
            ?? AcolhimentoSituacao::NaUnidade;
    }
}
