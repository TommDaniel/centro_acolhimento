<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'version', 'state', 'secret', 'last_accepted_time_step', 'confirmed_at', 'revoked_at'])]
#[Hidden(['secret', 'last_accepted_time_step'])]
class MfaEnrollment extends Model
{
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'last_accepted_time_step' => 'integer',
            'confirmed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
