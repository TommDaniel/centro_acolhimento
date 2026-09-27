<?php

namespace App\Http\Controllers;

use App\Models\Crianca;
use App\Services\AuditRecorder;
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

    public function __construct(private AuditRecorder $audit) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Crianca::class);

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);
        $term = trim($validated['q']);

        if ($term === '') {
            return redirect()->route('busca', status: 303);
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

        return redirect()->route('busca', ['searchId' => $searchId], 303);
    }

    public function index(Request $request, ?string $searchId = null): Response|RedirectResponse
    {
        $this->authorize('viewAny', Crianca::class);

        if ($request->query->has('q')) {
            return redirect()->route('busca', $searchId === null ? [] : ['searchId' => $searchId], 303);
        }

        if ($searchId === null) {
            $request->session()->put(self::SEARCHES_SESSION_KEY, $this->activeSearches($request, now()->getTimestamp()));

            return Inertia::render('Busca', ['criancas' => null]);
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

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search['term']).'%';

        $criancas = Crianca::query()
            ->select(['id', 'nome_completo', 'data_nascimento', 'processo_numero', 'status'])
            ->where(function ($where) use ($like) {
                $where->where('nome_completo', 'like', $like)
                    ->orWhere('nome_social', 'like', $like)
                    ->orWhere('processo_numero', 'like', $like)
                    ->orWhere('rg', 'like', $like)
                    ->orWhere('cpf', 'like', $like)
                    ->orWhere('nome_mae', 'like', $like)
                    ->orWhere('nome_pai', 'like', $like)
                    ->orWhere('responsavel_legal', 'like', $like);
            })
            ->withCount(['pias', 'reports', 'visitasTecnicas', 'pertences'])
            ->orderBy('nome_completo')
            ->paginate(15)
            ->through(static fn (Crianca $crianca): array => [
                'id' => $crianca->id,
                'nome_completo' => $crianca->nome_completo,
                'data_nascimento' => $crianca->data_nascimento?->toDateString(),
                'processo_numero' => $crianca->processo_numero,
                'status' => $crianca->status,
                'pias_count' => (int) $crianca->pias_count,
                'reports_count' => (int) $crianca->reports_count,
                'visitas_tecnicas_count' => (int) $crianca->visitas_tecnicas_count,
                'pertences_count' => (int) $crianca->pertences_count,
            ])
            ->withPath(route('busca', ['searchId' => $searchId]));

        $this->audit->record('search.executed', 'success', $request->user());

        return Inertia::render('Busca', ['criancas' => $criancas]);
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
