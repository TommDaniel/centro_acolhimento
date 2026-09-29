<?php

namespace App\Http\Controllers;

use App\Enums\CriancaSituacaoFiltro;
use App\Http\Requests\StoreProtectedSearchRequest;
use App\Models\Crianca;
use App\Services\AuditRecorder;
use App\Services\ProtectedSearchQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const SEARCHES_SESSION_KEY = 'search.query_handles';

    private const MAX_SEARCHES_PER_SESSION = 5;

    private const SEARCH_EXPIRY_MINUTES = 10;

    public function __construct(
        private AuditRecorder $audit,
        private ProtectedSearchQuery $searchQuery,
    ) {}

    public function store(StoreProtectedSearchRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $term = trim($validated['q']);
        $situacao = CriancaSituacaoFiltro::tryFrom($validated['situacao'] ?? 'todos')
            ?? CriancaSituacaoFiltro::Todos;

        if ($term === '') {
            $parameters = array_key_exists('situacao', $validated)
                ? ['situacao' => $situacao->value]
                : [];

            return redirect()->route('busca', $parameters, 303);
        }

        $now = now();
        $searches = $this->activeSearches($request, $now->getTimestamp());
        $searchId = Str::random(64);
        $searches[$searchId] = [
            'term' => $term,
            'expires_at' => $now->addMinutes(self::SEARCH_EXPIRY_MINUTES)->getTimestamp(),
        ];
        $searches = array_slice($searches, -self::MAX_SEARCHES_PER_SESSION, null, true);
        $request->session()->put(self::SEARCHES_SESSION_KEY, $searches);

        $parameters = ['searchId' => $searchId];

        if (array_key_exists('situacao', $validated)) {
            $parameters['situacao'] = $situacao->value;
        }

        return redirect()->route('busca', $parameters, 303);
    }

    public function index(Request $request, ?string $searchId = null): Response|RedirectResponse
    {
        $this->authorize('viewAny', Crianca::class);

        $rawSituation = $request->query('situacao', 'todos');
        $situacao = is_string($rawSituation)
            ? CriancaSituacaoFiltro::tryFrom($rawSituation)
            : null;

        if ($situacao === null) {
            return redirect()->route('busca', $searchId === null ? [] : ['searchId' => $searchId], 303);
        }

        if ($request->query->has('q')) {
            $parameters = $searchId === null ? [] : ['searchId' => $searchId];

            if ($request->query->has('situacao')) {
                $parameters['situacao'] = $situacao->value;
            }

            return redirect()->route('busca', $parameters, 303);
        }

        if ($searchId === null) {
            $request->session()->put(self::SEARCHES_SESSION_KEY, $this->activeSearches($request, now()->getTimestamp()));

            return Inertia::render('Busca', [
                'criancas' => null,
                'situacao' => $situacao->value,
                'filtrosSituacao' => [],
            ]);
        }

        if (preg_match('/\A[a-zA-Z0-9]{64}\z/', $searchId) !== 1) {
            return redirect()->route('busca', status: 303);
        }

        $searches = $this->activeSearches($request, now()->getTimestamp());
        $request->session()->put(self::SEARCHES_SESSION_KEY, $searches);
        $search = $searches[$searchId] ?? null;

        if ($search === null) {
            return redirect()->route('busca', status: 303);
        }

        $results = $this->searchQuery->search($search['term'], $situacao);
        $criancas = $results['criancas']
            ->withPath(route('busca', ['searchId' => $searchId]));

        $filtrosSituacao = array_map(
            fn (array $option): array => [
                ...$option,
                'href' => route('busca', [
                    'searchId' => $searchId,
                    'situacao' => $option['value'],
                ]),
            ],
            $results['filtros'],
        );

        $this->audit->record('search.executed', 'success', $request->user());

        return Inertia::render('Busca', [
            'criancas' => $criancas,
            'situacao' => $situacao->value,
            'filtrosSituacao' => $filtrosSituacao,
        ]);
    }

    /**
     * @return array<string, array{term: string, expires_at: int}>
     */
    private function activeSearches(Request $request, int $timestamp): array
    {
        $searches = $request->session()->get(self::SEARCHES_SESSION_KEY, []);

        if (! is_array($searches)) {
            return [];
        }

        return array_filter($searches, static fn (mixed $search): bool => is_array($search)
            && is_string($search['term'] ?? null)
            && is_int($search['expires_at'] ?? null)
            && $search['expires_at'] > $timestamp
        );
    }
}
