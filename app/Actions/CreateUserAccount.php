<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateUserAccount
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($attributes, $actor): User {
            User::query()
                ->where('role', UserRole::Administradora->value)
                ->where('status', UserStatus::Ativa->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());

            if (! $lockedActor->isAdministrator()) {
                throw new AuthorizationException;
            }

            $attributes['status'] = UserStatus::PendenteMfa->value;
            $user = User::query()->create($attributes);
            $this->audit->record('user.created', 'success', $lockedActor, $user, array_keys($attributes));

            return $user;
        });
    }
}
