<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'consecutive_failures', 'cooldown_level', 'blocked_until', 'last_failed_at'])]
class MfaAttemptState extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $attributes = [
        'consecutive_failures' => 0,
        'cooldown_level' => 0,
    ];

    protected function casts(): array
    {
        return [
            'consecutive_failures' => 'integer',
            'cooldown_level' => 'integer',
            'blocked_until' => 'immutable_datetime',
            'last_failed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
