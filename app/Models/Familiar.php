<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'crianca_id', 'tipo', 'nome', 'parentesco', 'data_nascimento',
    'rg', 'cpf', 'telefone', 'endereco', 'ocupacao', 'observacoes',
])]
class Familiar extends Model
{
    protected $table = 'familiares';

    protected $appends = ['idade'];

    public const TIPOS = [
        'genitora' => 'Genitora',
        'genitor' => 'Genitor',
        'responsavel' => 'Responsável',
        'familiar' => 'Familiar',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
        ];
    }

    protected function idade(): Attribute
    {
        return Attribute::get(fn () => $this->data_nascimento?->age);
    }

    public function crianca(): BelongsTo
    {
        return $this->belongsTo(Crianca::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }
}
