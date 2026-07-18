<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'crianca_id', 'composicao_familiar', 'dados_acolhimento',
    'acolhimento_anterior', 'acolhimento_anterior_detalhes', 'encaminhado_por',
    'especificidades', 'informacoes_familia', 'saude', 'saude_familiares',
    'educacao_menor', 'educacao_familiares', 'assistencia_social',
    'assistencia_social_familiares', 'esporte_cultura_lazer',
    'consideracoes_tecnicas', 'plano_acao', 'providencias_judiciario', 'setor_id', 'numero_oficio',
])]
class Pia extends Model
{
    protected function casts(): array
    {
        return [
            'acolhimento_anterior' => 'boolean',
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

    public function anexos(): HasMany
    {
        return $this->hasMany(PiaAnexo::class)->latest();
    }

    /**
     * Seções de texto livre do PIA (vazias não aparecem no documento).
     * Identificação, filiação, composição familiar e dados do acolhimento
     * são montadas separadamente a partir do cadastro da criança.
     *
     * @return array<string, string>
     */
    public function secoes(): array
    {
        return array_filter([
            'Especificidades da criança/adolescente acolhido' => $this->especificidades,
            'Informações relevantes sobre a família' => $this->informacoes_familia,
            'Saúde' => $this->saude,
            'Saúde dos familiares' => $this->saude_familiares,
            'Educação e profissionalização do menor' => $this->educacao_menor,
            'Educação e profissionalização dos familiares' => $this->educacao_familiares,
            'Assistência social' => $this->assistencia_social,
            'Assistência social dos familiares' => $this->assistencia_social_familiares,
            'Esporte, cultura e lazer' => $this->esporte_cultura_lazer,
            'Considerações técnicas' => $this->consideracoes_tecnicas,
            'Plano de ação' => $this->plano_acao,
            'Providências/demandas ao Judiciário' => $this->providencias_judiciario,
        ], fn (?string $valor) => filled($valor));
    }
}
