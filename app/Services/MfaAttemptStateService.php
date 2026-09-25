<?php

namespace App\Services;

use App\Exceptions\MfaCooldownException;
use App\Models\MfaAttemptState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MfaAttemptStateService
{
    private const MAX_FAILURES = 65535;

    /** @var array<int, int> */
    private const COOLDOWN_SECONDS_BY_LEVEL = [
        1 => 300,
        2 => 900,
        3 => 3600,
    ];

    public function lockFor(User $lockedUser): MfaAttemptState
    {
        $state = MfaAttemptState::query()
            ->whereKey($lockedUser->getKey())
            ->lockForUpdate()
            ->first();

        if ($state !== null) {
            return $state;
        }

        return MfaAttemptState::query()->create([
            'user_id' => $lockedUser->getKey(),
            'consecutive_failures' => 0,
            'cooldown_level' => 0,
        ]);
    }

    public function ensureAttemptIsAllowed(MfaAttemptState $state): void
    {
        $retryAfterSeconds = $this->remainingCooldownSeconds($state);

        if ($retryAfterSeconds === null) {
            return;
        }

        throw new MfaCooldownException($retryAfterSeconds);
    }

    public function activeCooldownSeconds(User $user): ?int
    {
        return DB::transaction(function () use ($user): ?int {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $state = MfaAttemptState::query()
                ->whereKey($lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            return $state === null ? null : $this->remainingCooldownSeconds($state);
        });
    }

    /**
     * @return array{retry_after_seconds: int, cooldown_level: int}
     */
    public function recordFailure(MfaAttemptState $state): array
    {
        $failures = min(self::MAX_FAILURES, $state->consecutive_failures + 1);
        $cooldownLevel = match (true) {
            $failures >= 15 => 3,
            $failures >= 10 => 2,
            $failures >= 5 => 1,
            default => 0,
        };
        $retryAfterSeconds = self::COOLDOWN_SECONDS_BY_LEVEL[$cooldownLevel] ?? 0;

        $state->update([
            'consecutive_failures' => $failures,
            'cooldown_level' => $cooldownLevel,
            'blocked_until' => $retryAfterSeconds === 0 ? null : now('UTC')->addSeconds($retryAfterSeconds),
            'last_failed_at' => now('UTC'),
        ]);

        return [
            'retry_after_seconds' => $retryAfterSeconds,
            'cooldown_level' => $cooldownLevel,
        ];
    }

    public function resetAfterSuccessfulFactor(MfaAttemptState $state): void
    {
        if ($state->consecutive_failures === 0 && $state->blocked_until === null) {
            return;
        }

        $state->update([
            'consecutive_failures' => 0,
            'cooldown_level' => 0,
            'blocked_until' => null,
            'last_failed_at' => null,
        ]);
    }

    private function remainingCooldownSeconds(MfaAttemptState $state): ?int
    {
        if ($state->blocked_until === null || ! $state->blocked_until->isFuture()) {
            return null;
        }

        return max(1, (int) ceil(now('UTC')->diffInSeconds($state->blocked_until, false)));
    }
}
