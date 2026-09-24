<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use App\Support\AuditOperation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasApprovedAccess
{
    public function __construct(private AuditRecorder $audit) {}

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
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort(403, 'Esta conta não possui acesso ativo ao sistema.');
        }

        return $next($request);
    }
}
