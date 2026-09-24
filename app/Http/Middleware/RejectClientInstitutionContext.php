<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RejectClientInstitutionContext
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $query = $request->query->all();
        $routeParameters = $request->route()?->parameters() ?? [];

        if (
            $this->containsContextSelector($body)
            || $this->containsContextSelector($query)
            || $this->containsContextSelector($routeParameters)
        ) {
            $this->audit->record(
                'access.denied.institution_context_override',
                'denied',
                $request->user(),
            );
            $request->attributes->set('_access_denial_audited', true);

            return response()->json([
                'message' => 'A solicitação contém um campo não permitido.',
                'errors' => [
                    'contexto' => ['O contexto institucional não pode ser selecionado nesta solicitação.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $next($request);
    }

    /** @param array<string|int, mixed> $values */
    private function containsContextSelector(array $values, ?string $contextContainer = null): bool
    {
        foreach ($values as $key => $value) {
            $normalizedKey = is_string($key) ? $this->normalizeKey($key) : null;

            if ($normalizedKey !== null) {
                if ($this->isContextIdKey($normalizedKey)) {
                    return true;
                }

                if (! is_array($value) && $this->isUnambiguousContextAlias($normalizedKey)) {
                    return true;
                }

                if ($contextContainer !== null && $normalizedKey === 'id') {
                    return true;
                }
            }

            if (is_array($value) && $this->containsContextSelector(
                $value,
                $normalizedKey !== null && $this->isContextContainer($normalizedKey)
                    ? $normalizedKey
                    : (is_int($key) ? $contextContainer : null),
            )) {
                return true;
            }
        }

        return false;
    }

    private function normalizeKey(string $key): string
    {
        return Str::of($key)
            ->ascii()
            ->replace(['.', '-', '[', ']'], '_')
            ->snake()
            ->lower()
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    private function isContextIdKey(string $normalizedKey): bool
    {
        $aliases = [
            'organizacao_id',
            'organization_id',
            'organisation_id',
            'org_id',
            'unidade_id',
            'unit_id',
            'tenant_id',
        ];

        return collect($aliases)->contains(
            fn (string $alias): bool => $normalizedKey === $alias || str_ends_with($normalizedKey, '_'.$alias),
        );
    }

    private function isContextContainer(string $normalizedKey): bool
    {
        return in_array($normalizedKey, [
            'organizacao',
            'organization',
            'organisation',
            'org',
            'unidade',
            'unit',
            'tenant',
        ], true);
    }

    private function isUnambiguousContextAlias(string $normalizedKey): bool
    {
        return in_array($normalizedKey, [
            'organizacao',
            'organization',
            'organisation',
            'org',
            'unidade',
            'tenant',
        ], true);
    }
}
