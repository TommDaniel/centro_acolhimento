<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nome_original', 'path', 'mime', 'tamanho', 'uploaded_by'])]
#[Hidden(['path'])]
class CriancaDocumento extends Model
{
    public function crianca(): BelongsTo
    {
        return $this->belongsTo(Crianca::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
