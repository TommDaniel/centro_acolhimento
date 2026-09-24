<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectRememberedSession
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if (! $guard->viaRemember()) {
            return $next($request);
        }

        $user = $request->user();

        $this->audit->record('auth.remember_rejected', 'denied', $user, $user);
        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new RedirectResponse(route('login'));
    }
}
