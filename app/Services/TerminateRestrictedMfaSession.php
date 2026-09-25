<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TerminateRestrictedMfaSession
{
    public function __construct(private TerminateAuthenticatedSession $terminateSession) {}

    public function tooManyAttempts(Request $request, int $retryAfterSeconds): Response
    {
        $this->terminateSession->handle($request);

        return response('Muitas tentativas. Faça login novamente mais tarde.', 429, [
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'Retry-After' => (string) max(1, $retryAfterSeconds),
            'X-Mfa-Session-Expired' => '1',
        ]);
    }

    public function revoked(Request $request): Response
    {
        $this->terminateSession->handle($request);

        return redirect()->route('login')->with('status', 'Sua sessão de autenticação foi encerrada. Entre novamente.');
    }
}
