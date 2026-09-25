<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use App\Services\TerminateAuthenticatedSession;
use App\Support\AuditOperation;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerifiedSession
{
    public function __construct(
        private AuditRecorder $audit,
        private TerminateAuthenticatedSession $terminateSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $now = now('UTC')->getTimestamp();
        $issuedAt = $request->session()->get('auth.mfa_issued_at');
        $lastActivityAt = $request->session()->get('auth.mfa_last_activity_at');
        $generation = $request->session()->get('auth.access_generation');
        $passwordVerifiedAt = $request->session()->get('auth.password_verified_at');
        $idleLifetime = app()->environment('testing')
            ? max(1, (int) config('fortify.verified_session_idle_ttl_seconds', 900))
            : 900;
        $absoluteLifetime = app()->environment('testing')
            ? max(1, (int) config('fortify.verified_session_absolute_ttl_seconds', 28800))
            : 28800;

        $valid = $user !== null
            && $user->hasApprovedAccess()
            && $user->hasActiveMfa()
            && $request->session()->get('auth.level') === 'mfa_verified'
            && is_int($issuedAt)
            && is_int($lastActivityAt)
            && $now - $lastActivityAt <= $idleLifetime
            && $now - $issuedAt <= $absoluteLifetime
            && is_int($generation)
            && $generation === $user->access_generation;

        if (! $valid) {
            $isRestrictedAuthenticatedSession = $user !== null
                && $request->session()->get('auth.level') === 'password_only'
                && is_int($generation)
                && $generation === $user->access_generation
                && is_int($passwordVerifiedAt)
                && $now - $passwordVerifiedAt <= 300;

            if ($isRestrictedAuthenticatedSession) {
                $this->audit->record('auth.mfa_required', 'denied', $user, $user);
                abort(403, 'A autenticação em duas etapas é obrigatória para acessar esta área.');
            }

            if ($user !== null) {
                if (! $user->hasApprovedAccess()) {
                    $action = AuditOperation::denied($request->route()?->getName());
                    $request->attributes->set('_access_denial_audited', true);
                } elseif (! is_int($generation) || $generation !== $user->access_generation) {
                    $action = 'auth.session_generation_revoked';
                } elseif ($request->session()->get('auth.level') === 'password_only') {
                    $action = 'auth.mfa_restricted_expired';
                } else {
                    $action = 'auth.mfa_session_expired';
                }

                $this->audit->record($action, 'denied', $user, $user);
            }

            $this->terminateSession->handle($request);

            if ($user?->hasApprovedAccess() === false) {
                abort(403, 'A autenticação em duas etapas é obrigatória para acessar esta área.');
            }

            return new RedirectResponse(route('login'));
        }

        $request->session()->put('auth.mfa_last_activity_at', $now);

        return $next($request);
    }
}
