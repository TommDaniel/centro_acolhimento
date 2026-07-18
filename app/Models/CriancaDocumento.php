<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['nome_original', 'path', 'mime', 'tamanho', 'uploaded_by'])]
class CriancaDocumento extends Model
{
    protected $appends = ['url'];

    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->path ? Storage::url($this->path) : null);
    }

    public function crianca(): BelongsTo
    {
        return $this->belongsTo(Crianca::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
