<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use App\Services\TerminateAuthenticatedSession;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Session\Middleware\AuthenticateSession;

class AuthenticateSessionWithHistoryPurge extends AuthenticateSession
{
    public function __construct(
        AuthFactory $auth,
        private TerminateAuthenticatedSession $terminateSession,
        private AuditRecorder $audit,
    ) {
        parent::__construct($auth);
    }

    protected function logout($request): void
    {
        $user = $request->user();

        if ($user !== null) {
            $this->audit->record('auth.password_hash_revoked', 'denied', $user, $user);
        }

        $this->terminateSession->handle($request);

        throw new AuthenticationException(
            'Unauthenticated.',
            [$this->auth->getDefaultDriver()],
            $this->redirectTo($request),
        );
    }
}
