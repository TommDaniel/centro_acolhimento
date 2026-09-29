<?php

namespace App\Models;

use Database\Factories\CriancaInformacaoEscolarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'situacao_codigo', 'situacao_complemento', 'escola_nome', 'rede_codigo',
    'rede_complemento', 'matricula', 'ano_serie', 'turma', 'turno_codigo',
    'turno_complemento', 'vigente_em', 'fonte_codigo', 'fonte_complemento',
    'idempotency_key',
])]
class CriancaInformacaoEscolar extends Model
{
    /** @use HasFactory<CriancaInformacaoEscolarFactory> */
    use HasFactory;

    public const SITUACOES = [
        'matriculada' => 'Matriculada',
        'nao_matriculada' => 'Não matriculada',
        'matricula_em_andamento' => 'Matrícula em andamento',
        'frequencia_interrompida' => 'Frequência interrompida',
        'outra' => 'Outra',
        'nao_informada' => 'Informação não disponível',
    ];

    public const REDES = [
        'municipal' => 'Municipal',
        'estadual' => 'Estadual',
        'federal' => 'Federal',
        'privada' => 'Privada',
        'outra' => 'Outra',
    ];

    public const TURNOS = [
        'matutino' => 'Matutino',
        'vespertino' => 'Vespertino',
        'noturno' => 'Noturno',
        'integral' => 'Integral',
        'outro' => 'Outro',
    ];

    public const FONTES = [
        'crianca_adolescente' => 'Criança ou adolescente',
        'familiar_responsavel' => 'Familiar ou responsável',
        'escola' => 'Escola',
        'documento' => 'Documento',
        'rede_atendimento' => 'Rede de atendimento',
        'outra' => 'Outra',
        'nao_informada' => 'Fonte não informada',
    ];

    public $timestamps = false;

    protected $table = 'crianca_informacoes_escolares';

    protected function casts(): array
    {
        return [
            'vigente_em' => 'immutable_date',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function crianca(): BelongsTo
    {
        return $this->belongsTo(Crianca::class);
    }

    public function versaoAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'versao_anterior_id');
    }

    public function proximaVersao(): HasOne
    {
        return $this->hasOne(self::class, 'versao_anterior_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
