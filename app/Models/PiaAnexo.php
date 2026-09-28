<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pia_id', 'nome_original', 'descricao', 'path', 'mime', 'tamanho', 'uploaded_by'])]
#[Hidden(['path'])]
class PiaAnexo extends Model
{
    public function pia(): BelongsTo
    {
        return $this->belongsTo(Pia::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
