<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\PasswordResetTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EstablishPasswordOnlyLogin
{
    public function __construct(
        private AuditRecorder $audit,
        private PasswordResetTokenService $passwordResetTokens,
    ) {}

    public function handle(
        User $authenticatedUser,
        int $expectedGeneration,
        string $expectedPasswordHash,
        string $plainPassword,
    ): User {
        /** @var array{status: string, user: User} $outcome */
        $outcome = DB::transaction(function () use (
            $authenticatedUser,
            $expectedGeneration,
            $expectedPasswordHash,
            $plainPassword,
        ): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($authenticatedUser->getKey());
            $proofIsCurrent = $lockedUser->access_generation === $expectedGeneration
                && hash_equals($lockedUser->getAuthPassword(), $expectedPasswordHash)
                && Hash::check($plainPassword, $lockedUser->getAuthPassword());
            $mayAuthenticate = in_array($lockedUser->status, [UserStatus::Ativa, UserStatus::PendenteMfa], true)
                && in_array($lockedUser->role, [UserRole::Administradora, UserRole::EquipeTecnica], true);

            if (! $proofIsCurrent || ! $mayAuthenticate) {
                return ['status' => 'revoked', 'user' => $lockedUser];
            }

            if (! $lockedUser->hasActiveMfa() && $lockedUser->status === UserStatus::Ativa) {
                $this->passwordResetTokens->revokeLocked($lockedUser);
                $lockedUser->status = UserStatus::PendenteMfa;
                $lockedUser->access_generation++;
                $lockedUser->save();
            }

            return ['status' => 'success', 'user' => $lockedUser];
        });

        if ($outcome['status'] === 'success') {
            return $outcome['user'];
        }

        $this->audit->record('auth.password_proof_revoked', 'denied', subject: $outcome['user']);

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }
}
