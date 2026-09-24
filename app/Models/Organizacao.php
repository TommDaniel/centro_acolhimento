<?php

namespace App\Models;

use Database\Factories\OrganizacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nome'])]
class Organizacao extends Model
{
    /** @use HasFactory<OrganizacaoFactory> */
    use HasFactory;

    protected $table = 'organizacoes';

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }

    public function criancas(): HasMany
    {
        return $this->hasMany(Crianca::class);
    }
}
