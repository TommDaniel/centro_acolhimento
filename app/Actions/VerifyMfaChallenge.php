<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Exceptions\MfaCooldownException;
use App\Exceptions\MfaSessionRevokedException;
use App\Models\MfaEnrollment;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\MfaAttemptStateService;
use App\Services\TotpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VerifyMfaChallenge
{
    public function __construct(
        private TotpService $totp,
        private AuditRecorder $audit,
        private MfaAttemptStateService $attempts,
    ) {}

    public function handle(
        User $user,
        string $code,
        ?int $expectedGeneration = null,
        string $expectedLevel = 'password_only',
    ): User {
        $expectedGeneration ??= $user->access_generation;

        /** @var array{status: string, user: User, retry_after_seconds?: int, cooldown_level?: int} $outcome */
        $outcome = DB::transaction(function () use ($user, $code, $expectedGeneration, $expectedLevel): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($expectedLevel !== 'password_only'
                || $lockedUser->access_generation !== $expectedGeneration
                || $lockedUser->status !== UserStatus::Ativa) {
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

            $factor = MfaEnrollment::query()
                ->whereBelongsTo($lockedUser)
                ->where('state', 'active')
                ->lockForUpdate()
                ->first();

            $acceptedTimeStep = $factor === null ? false : $this->totp->verifyNewer(
                $factor->secret,
                $code,
                $factor->last_accepted_time_step,
            );

            if ($factor === null) {
                return ['status' => 'invalid', 'user' => $lockedUser];
            }

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

            $factor->update(['last_accepted_time_step' => $acceptedTimeStep]);
            $this->attempts->resetAfterSuccessfulFactor($attemptState);
            $this->audit->record('auth.mfa_challenge', 'success', $lockedUser, $lockedUser);

            return ['status' => 'success', 'user' => $lockedUser];
        });

        if ($outcome['status'] === 'success') {
            return $outcome['user'];
        }

        $this->audit->record('auth.mfa_challenge', 'denied', $outcome['user'], $outcome['user']);

        if ($outcome['status'] === 'revoked') {
            $this->audit->record('auth.session_generation_revoked', 'denied', subject: $outcome['user']);

            throw new MfaSessionRevokedException;
        }

        if (str_starts_with($outcome['status'], 'cooldown_')) {
            if ($outcome['status'] === 'cooldown_started') {
                Log::warning('Progressive MFA cooldown activated.', [
                    'security_event' => 'auth.mfa_cooldown_started',
                    'verification_flow' => 'challenge',
                    'cooldown_level' => $outcome['cooldown_level'],
                    'retry_after_seconds' => $outcome['retry_after_seconds'],
                ]);
            }

            throw new MfaCooldownException($outcome['retry_after_seconds']);
        }

        if ($outcome['status'] === 'invalid') {
            throw ValidationException::withMessages(['code' => 'Código de segurança inválido ou já utilizado.']);
        }

        throw new \LogicException('Resultado de verificação MFA desconhecido.');
    }
}
