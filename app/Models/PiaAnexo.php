<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['pia_id', 'nome_original', 'descricao', 'path', 'mime', 'tamanho', 'uploaded_by'])]
class PiaAnexo extends Model
{
    protected $appends = ['url'];

    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->path ? Storage::url($this->path) : null);
    }

    public function pia(): BelongsTo
    {
        return $this->belongsTo(Pia::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
