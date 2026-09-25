<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use App\Services\MfaAttemptStateService;
use App\Services\TerminateRestrictedMfaSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleMfaAttempt
{
    public function __construct(
        private AuditRecorder $audit,
        private MfaAttemptStateService $attempts,
        private TerminateRestrictedMfaSession $terminateSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $keys = $this->keys($request);

        if ($user !== null) {
            $durableRetryAfter = $this->attempts->activeCooldownSeconds($user);

            if ($durableRetryAfter !== null) {
                $this->audit->record('auth.mfa_cooldown_active', 'denied', $user, $user);

                return $this->terminateSession->tooManyAttempts($request, $durableRetryAfter);
            }
        }

        if (collect($keys)->contains(fn (string $key): bool => RateLimiter::tooManyAttempts($key, 5))) {
            if ($user !== null) {
                $this->audit->record('auth.mfa_rate_limited', 'denied', $user, $user);
            }

            $retryAfter = collect($keys)
                ->map(fn (string $key): int => RateLimiter::availableIn($key))
                ->max();

            return $this->terminateSession->tooManyAttempts($request, max(1, $retryAfter));
        }

        foreach ($keys as $key) {
            RateLimiter::hit($key, 60);
        }

        $response = $next($request);

        if ($request->session()->get('auth.level') === 'mfa_verified') {
            foreach ($keys as $key) {
                RateLimiter::clear($key);
            }
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    private function keys(Request $request): array
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $session = hash('sha256', (string) $request->session()->get('auth.restricted_session_id', $request->session()->getId()));
        $ip = hash('sha256', (string) $request->ip());

        return [
            "mfa|account|{$userId}",
            "mfa|account_ip|{$userId}|{$ip}",
            "mfa|session|{$userId}|{$session}|{$ip}",
        ];
    }
}
