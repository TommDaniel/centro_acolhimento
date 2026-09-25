<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Exceptions\MfaSessionRevokedException;
use App\Models\MfaEnrollment;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\TotpService;
use Illuminate\Support\Facades\DB;

class StartMfaEnrollment
{
    public function __construct(
        private TotpService $totp,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $user, ?int $expectedGeneration = null): MfaEnrollment
    {
        $expectedGeneration ??= $user->access_generation;

        $enrollment = DB::transaction(function () use ($user, $expectedGeneration): ?MfaEnrollment {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->access_generation !== $expectedGeneration
                || $lockedUser->status !== UserStatus::PendenteMfa
                || $lockedUser->hasActiveMfa()) {
                return null;
            }

            $revokedPending = MfaEnrollment::query()
                ->whereBelongsTo($lockedUser)
                ->where('state', 'pending')
                ->lockForUpdate()
                ->update([
                    'state' => 'revoked',
                    'revoked_at' => now('UTC'),
                ]);

            $nextVersion = (int) MfaEnrollment::query()
                ->whereBelongsTo($lockedUser)
                ->max('version') + 1;

            $enrollment = MfaEnrollment::query()->create([
                'user_id' => $lockedUser->getKey(),
                'version' => $nextVersion,
                'state' => 'pending',
                'secret' => $this->totp->generateSecret(),
            ]);

            $this->audit->record(
                $revokedPending > 0 ? 'auth.mfa_enrollment_rotated' : 'auth.mfa_enrollment_started',
                'success',
                $lockedUser,
                $lockedUser,
            );

            return $enrollment;
        });

        if ($enrollment === null) {
            $this->audit->record('auth.session_generation_revoked', 'denied', subject: $user);

            throw new MfaSessionRevokedException;
        }

        return $enrollment;
    }
}
