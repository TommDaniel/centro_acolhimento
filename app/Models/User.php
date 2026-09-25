<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'setor_id', 'role', 'status', 'cargo', 'telefone'])]
#[Hidden(['password', 'remember_token', 'access_generation'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = ['is_admin'];

    protected $attributes = [
        'role' => UserRole::EquipeTecnica->value,
        'status' => UserStatus::Ativa->value,
        'access_generation' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'access_generation' => 'integer',
        ];
    }

    protected function isAdmin(): Attribute
    {
        return Attribute::get(fn (): bool => $this->isAdministrator());
    }

    public function hasApprovedAccess(): bool
    {
        return $this->status === UserStatus::Ativa
            && in_array($this->role, [UserRole::Administradora, UserRole::EquipeTecnica], true);
    }

    public function isAdministrator(): bool
    {
        return $this->status === UserStatus::Ativa
            && $this->role === UserRole::Administradora;
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function mfaEnrollments(): HasMany
    {
        return $this->hasMany(MfaEnrollment::class);
    }

    public function mfaAttemptState(): HasOne
    {
        return $this->hasOne(MfaAttemptState::class);
    }

    public function hasActiveMfa(): bool
    {
        return $this->mfaEnrollments()->where('state', 'active')->exists();
    }
}
