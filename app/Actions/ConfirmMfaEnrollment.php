<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Exceptions\MfaCooldownException;
use App\Exceptions\MfaSessionRevokedException;
use App\Models\MfaEnrollment;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\MfaAttemptStateService;
use App\Services\PasswordResetTokenService;
use App\Services\TotpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ConfirmMfaEnrollment
{
    public function __construct(
        private TotpService $totp,
        private AuditRecorder $audit,
        private MfaAttemptStateService $attempts,
        private PasswordResetTokenService $passwordResetTokens,
    ) {}

    public function handle(
        User $user,
        int $enrollmentId,
        string $code,
        ?int $expectedGeneration = null,
        string $expectedLevel = 'password_only',
    ): User {
        $enforcesRestrictedSessionContract = $expectedGeneration !== null;
        $expectedGeneration ??= $user->access_generation;

        /** @var array{status: string, user: User, retry_after_seconds?: int, cooldown_level?: int} $outcome */
        $outcome = DB::transaction(function () use (
            $user,
            $enrollmentId,
            $code,
            $expectedGeneration,
            $expectedLevel,
        ): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($expectedLevel !== 'password_only'
                || $lockedUser->access_generation !== $expectedGeneration
                || $lockedUser->status !== UserStatus::PendenteMfa) {
                return ['status' => 'revoked', 'user' => $lockedUser];
            }

            $attemptState = $this->attempts->lockFor($lockedUser);

            try {
                $this->attempts->ensureAttemptIsAllowed($attemptState);
            } catch (MfaCooldownException $exception) {
                $this->audit->record('auth.mfa_cooldown_active', 'denied', $lockedUser, $lockedUser);

                return [
                    'status' => 'cooldown_active',
                    'user' => $lockedUser,
                    'retry_after_seconds' => $exception->retryAfterSeconds,
                    'cooldown_level' => $attemptState->cooldown_level,
                ];
            }

            $hasActiveFactor = MfaEnrollment::query()
                ->whereBelongsTo($lockedUser)
                ->where('state', 'active')
                ->lockForUpdate()
                ->exists();
            $enrollment = MfaEnrollment::query()
                ->whereKey($enrollmentId)
                ->whereBelongsTo($lockedUser)
                ->where('state', 'pending')
                ->lockForUpdate()
                ->first();

            if ($lockedUser->status !== UserStatus::PendenteMfa || $hasActiveFactor || $enrollment === null) {
                return ['status' => 'invalid', 'user' => $lockedUser];
            }

            $acceptedTimeStep = $this->totp->verifyNewer(
                $enrollment->secret,
                $code,
                $enrollment->last_accepted_time_step,
            );

            if ($acceptedTimeStep === false) {
                $failure = $this->attempts->recordFailure($attemptState);

                if ($failure['retry_after_seconds'] > 0) {
                    $this->audit->record('auth.mfa_rate_limited', 'denied', $lockedUser, $lockedUser);
                    $this->audit->record(
                        'auth.mfa_cooldown_started',
                        'denied',
                        $lockedUser,
                        $lockedUser,
                        ['blocked_until', 'consecutive_failures', 'cooldown_level'],
                    );

                    return [
                        'status' => 'cooldown_started',
                        'user' => $lockedUser,
                        ...$failure,
                    ];
                }

                return ['status' => 'invalid', 'user' => $lockedUser];
            }

            $this->passwordResetTokens->revokeLocked($lockedUser);

            $enrollment->update([
                'state' => 'active',
                'last_accepted_time_step' => $acceptedTimeStep,
                'confirmed_at' => now('UTC'),
            ]);

            $lockedUser->status = UserStatus::Ativa;
            $lockedUser->access_generation++;
            $lockedUser->save();

            $this->attempts->resetAfterSuccessfulFactor($attemptState);
            $this->audit->record('auth.mfa_enrollment_confirmed', 'success', $lockedUser, $lockedUser);

            return ['status' => 'success', 'user' => $lockedUser];
        });

        if ($outcome['status'] === 'success') {
            return $outcome['user'];
        }

        $this->audit->record('auth.mfa_enrollment_confirmed', 'denied', $outcome['user'], $outcome['user']);

        if ($outcome['status'] === 'revoked') {
            $this->audit->record('auth.session_generation_revoked', 'denied', subject: $outcome['user']);

            if ($enforcesRestrictedSessionContract) {
                throw new MfaSessionRevokedException;
            }

            throw ValidationException::withMessages([
                'code' => 'Código de segurança inválido, já utilizado ou vínculo indisponível.',
            ]);
        }

        if (str_starts_with($outcome['status'], 'cooldown_')) {
            if ($outcome['status'] === 'cooldown_started') {
                Log::warning('Progressive MFA cooldown activated.', [
                    'security_event' => 'auth.mfa_cooldown_started',
                    'verification_flow' => 'enrollment',
                    'cooldown_level' => $outcome['cooldown_level'],
                    'retry_after_seconds' => $outcome['retry_after_seconds'],
                ]);
            }

            throw new MfaCooldownException($outcome['retry_after_seconds']);
        }

        if ($outcome['status'] === 'invalid') {
            throw ValidationException::withMessages(['code' => 'Código de segurança inválido, já utilizado ou vínculo indisponível.']);
        }

        throw new \LogicException('Resultado de confirmação MFA desconhecido.');
    }
}
