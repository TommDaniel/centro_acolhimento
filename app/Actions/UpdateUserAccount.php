<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\PasswordResetTokenService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateUserAccount
{
    public function __construct(
        private AuditRecorder $audit,
        private PasswordResetTokenService $passwordResetTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $subject, array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($subject, $attributes, $actor): User {
            $activeAdministrators = User::query()
                ->where('role', UserRole::Administradora->value)
                ->where('status', UserStatus::Ativa->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());

            if (! $lockedActor->isAdministrator()) {
                throw new AuthorizationException;
            }

            $lockedSubject = User::query()->lockForUpdate()->findOrFail($subject->getKey());
            $requestedRole = UserRole::from($attributes['role']);
            $requestedStatus = UserStatus::from($attributes['status']);

            if ($requestedStatus === UserStatus::Ativa && ! $lockedSubject->hasActiveMfa()) {
                $requestedStatus = UserStatus::PendenteMfa;
                $attributes['status'] = $requestedStatus->value;
            }

            if ($requestedStatus === UserStatus::PendenteMfa && $lockedSubject->hasActiveMfa()) {
                throw ValidationException::withMessages([
                    'status' => 'Uma conta com MFA ativo não pode retornar ao estado pendente por esta tela.',
                ]);
            }

            if ($lockedSubject->is($lockedActor) && array_key_exists('password', $attributes)) {
                throw ValidationException::withMessages([
                    'password' => 'Use a alteração de senha do perfil, confirmando a senha atual.',
                ]);
            }

            if ($lockedSubject->is($lockedActor)
                && ($lockedSubject->role !== $requestedRole || $lockedSubject->status !== $requestedStatus)) {
                throw ValidationException::withMessages([
                    'role' => 'A administradora não pode alterar o próprio papel ou estado de acesso.',
                    'status' => 'A administradora não pode alterar o próprio papel ou estado de acesso.',
                ]);
            }

            $removesActiveAdministrator = $lockedSubject->role === UserRole::Administradora
                && $lockedSubject->status === UserStatus::Ativa
                && ($requestedRole !== UserRole::Administradora || $requestedStatus !== UserStatus::Ativa);

            if ($removesActiveAdministrator && $activeAdministrators->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Deve permanecer pelo menos uma administradora ativa.',
                    'status' => 'Deve permanecer pelo menos uma administradora ativa.',
                ]);
            }

            $lockedSubject->fill($attributes);

            if ($lockedSubject->isDirty('password')) {
                $lockedSubject->remember_token = Str::random(60);
            }

            if ($lockedSubject->isDirty(['password', 'email', 'status', 'role'])) {
                $lockedSubject->access_generation++;
                $this->passwordResetTokens->revokeLocked($lockedSubject);
            }

            $changedFields = array_keys($lockedSubject->getDirty());
            $lockedSubject->save();

            $accountFields = array_values(array_diff($changedFields, ['password', 'remember_token', 'access_generation']));

            if ($accountFields !== []) {
                $this->audit->record('user.updated', 'success', $lockedActor, $lockedSubject, $accountFields);
            }

            if (in_array('password', $changedFields, true)) {
                $this->audit->record('user.password_admin_reset', 'success', $lockedActor, $lockedSubject);
            }

            return $lockedSubject;
        });
    }
}
