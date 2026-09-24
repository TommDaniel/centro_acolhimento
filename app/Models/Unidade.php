<?php

namespace App\Models;

use Database\Factories\UnidadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nome'])]
class Unidade extends Model
{
    /** @use HasFactory<UnidadeFactory> */
    use HasFactory;

    protected $table = 'unidades';

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function setores(): HasMany
    {
        return $this->hasMany(Setor::class);
    }
}
