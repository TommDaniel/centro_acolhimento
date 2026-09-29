<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'nome_completo', 'nome_social', 'data_nascimento', 'sexo', 'identidade_genero', 'cor_raca',
    'naturalidade', 'nacionalidade', 'rg', 'cpf', 'certidao_nascimento', 'rn',
    'cartao_sus', 'nis', 'titulo_eleitor',
    'nome_mae', 'nome_pai', 'responsavel_legal', 'contato_responsavel',
    'endereco_familia', 'processo_numero', 'vara', 'comarca',
    'foto', 'observacoes',
])]
#[Hidden(['foto'])]
class Crianca extends Model
{
    protected $appends = ['idade'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'data_acolhimento' => 'date',
        ];
    }

    protected function idade(): Attribute
    {
        return Attribute::get(fn () => $this->data_nascimento?->age);
    }

    public function pias(): HasMany
    {
        return $this->hasMany(Pia::class);
    }

    public function visitasTecnicas(): HasMany
    {
        return $this->hasMany(VisitaTecnica::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function pertences(): HasMany
    {
        return $this->hasMany(Pertence::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(CriancaDocumento::class);
    }

    public function familiares(): HasMany
    {
        return $this->hasMany(Familiar::class)->orderBy('tipo')->orderBy('data_nascimento');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function atualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }

    public function acolhimentos(): HasMany
    {
        return $this->hasMany(Acolhimento::class)->orderBy('ingresso_em')->orderBy('id');
    }

    public function informacoesEscolares(): HasMany
    {
        return $this->hasMany(CriancaInformacaoEscolar::class);
    }

    public function ultimoAcolhimento(): HasOne
    {
        return $this->hasOne(Acolhimento::class)->ofMany([
            'ingresso_em' => 'max',
            'id' => 'max',
        ]);
    }

    /**
     * Bloco de identificação reutilizado no PIA e demais documentos.
     *
     * @return array<string, string|null>
     */
    public function identificacao(?Acolhimento $acolhimento = null): array
    {
        if ($acolhimento === null && $this->relationLoaded('ultimoAcolhimento')) {
            $acolhimento = $this->ultimoAcolhimento;
        }

        return [
            'Nome completo' => $this->nome_completo,
            'Nome social' => $this->nome_social,
            'Data de nascimento' => $this->data_nascimento?->format('d/m/Y'),
            'Idade' => $this->idade !== null ? $this->idade.' anos' : null,
            'Sexo' => $this->sexo,
            'Identidade de gênero' => $this->identidade_genero,
            'Cor/Raça' => $this->cor_raca,
            'Naturalidade' => $this->naturalidade,
            'Nacionalidade' => $this->nacionalidade,
            'RG' => $this->rg,
            'CPF' => $this->cpf,
            'Certidão de nascimento' => $this->certidao_nascimento,
            'RN' => $this->rn,
            'Cartão do SUS' => $this->cartao_sus,
            'NIS' => $this->nis,
            'Título de eleitor' => $this->titulo_eleitor,
            'Nome da mãe' => $this->nome_mae,
            'Nome do pai' => $this->nome_pai,
            'Responsável legal' => $this->responsavel_legal,
            'Contato do responsável' => $this->contato_responsavel,
            'Endereço da família' => $this->endereco_familia,
            'Nº do processo' => $acolhimento === null
                ? $this->processo_numero
                : $acolhimento->processo_numero_snapshot,
            'Vara' => $acolhimento === null ? $this->vara : $acolhimento->vara_snapshot,
            'Comarca' => $acolhimento === null ? $this->comarca : $acolhimento->comarca_snapshot,
            ($acolhimento === null ? 'Data anterior de acolhimento (a conferir)' : 'Data de ingresso') => $acolhimento?->ingresso_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')
                ?? $this->data_acolhimento?->format('d/m/Y'),
        ];
    }
}
