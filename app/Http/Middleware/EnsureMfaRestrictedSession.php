<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Services\AuditRecorder;
use App\Services\TerminateAuthenticatedSession;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaRestrictedSession
{
    public function __construct(
        private AuditRecorder $audit,
        private TerminateAuthenticatedSession $terminateSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $passwordVerifiedAt = $request->session()->get('auth.password_verified_at');
        $generation = $request->session()->get('auth.access_generation');
        $level = $request->session()->get('auth.level');

        $now = now('UTC')->getTimestamp();
        $invalidGeneration = $user !== null && (! is_int($generation) || $generation !== $user->access_generation);
        $invalidLevel = $level !== 'password_only';
        $restrictedLifetime = app()->environment('testing')
            ? max(1, (int) config('fortify.restricted_session_ttl_seconds', 300))
            : 300;
        $expired = ! is_int($passwordVerifiedAt) || $now - $passwordVerifiedAt > $restrictedLifetime;

        if ($user === null || $user->status === UserStatus::Inativa || $expired || $invalidGeneration || $invalidLevel) {
            if ($user !== null) {
                $this->audit->record(
                    match (true) {
                        $invalidGeneration => 'auth.session_generation_revoked',
                        $user->status === UserStatus::Inativa => 'auth.account_inactive',
                        $invalidLevel => 'auth.mfa_restricted_invalid_level',
                        default => 'auth.mfa_restricted_expired',
                    },
                    'denied',
                    $user,
                    $user,
                );
            }
            $this->terminateSession->handle($request);

            return new RedirectResponse(route('login'));
        }

        return $next($request);
    }
}
