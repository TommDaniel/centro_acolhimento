<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use App\Services\TerminateAuthenticatedSession;
use App\Support\AuditOperation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasApprovedAccess
{
    public function __construct(
        private AuditRecorder $audit,
        private TerminateAuthenticatedSession $terminateSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasApprovedAccess()) {
            if ($user !== null) {
                $this->audit->record(
                    AuditOperation::denied($request->route()?->getName()),
                    'denied',
                    $user,
                    $user,
                );
                $request->attributes->set('_access_denial_audited', true);
                $this->terminateSession->handle($request);
            }

            abort(403, 'Esta conta não possui acesso ativo ao sistema.');
        }

        return $next($request);
    }
}
