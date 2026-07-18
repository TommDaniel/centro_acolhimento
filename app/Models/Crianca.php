<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'nome_completo', 'nome_social', 'data_nascimento', 'sexo', 'identidade_genero', 'cor_raca',
    'naturalidade', 'nacionalidade', 'rg', 'cpf', 'certidao_nascimento', 'rn',
    'cartao_sus', 'nis', 'titulo_eleitor',
    'nome_mae', 'nome_pai', 'responsavel_legal', 'contato_responsavel',
    'endereco_familia', 'processo_numero', 'vara', 'comarca',
    'data_acolhimento', 'motivo_acolhimento', 'foto', 'status', 'observacoes',
])]
class Crianca extends Model
{
    protected $appends = ['foto_url', 'idade'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'data_acolhimento' => 'date',
        ];
    }

    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->foto ? Storage::url($this->foto) : null);
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

    /**
     * Bloco de identificação reutilizado no PIA e demais documentos.
     *
     * @return array<string, string|null>
     */
    public function identificacao(): array
    {
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
            'Nº do processo' => $this->processo_numero,
            'Vara' => $this->vara,
            'Comarca' => $this->comarca,
            'Data de acolhimento' => $this->data_acolhimento?->format('d/m/Y'),
        ];
    }
}
