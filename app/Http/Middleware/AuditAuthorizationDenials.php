<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use App\Support\AuditOperation;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAuthorizationDenials
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 403
            && $request->user() !== null
            && ! $request->attributes->getBoolean('_access_denial_audited')) {
            $subject = collect($request->route()?->parameters() ?? [])
                ->first(fn (mixed $parameter): bool => $parameter instanceof Model);

            $this->audit->record(
                AuditOperation::denied($request->route()?->getName()),
                'denied',
                $request->user(),
                $subject instanceof Model ? $subject : null,
            );
            $request->attributes->set('_access_denial_audited', true);
        }

        return $response;
    }
}
