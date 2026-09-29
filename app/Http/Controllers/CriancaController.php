<?php

namespace App\Http\Controllers;

use App\Actions\CreateCrianca;
use App\Actions\StorePrivatePortrait;
use App\Actions\UpdateCrianca;
use App\Enums\CriancaSituacaoFiltro;
use App\Http\Requests\UpsertCriancaRequest;
use App\Models\Acolhimento;
use App\Models\Crianca;
use App\Models\CriancaDocumento;
use App\Models\CriancaInformacaoEscolar;
use App\Models\Familiar;
use App\Services\AcolhimentoProjection;
use App\Services\AuditRecorder;
use App\Services\CriancaInformacaoEscolarHistory;
use App\Services\CriancaSituacaoQuery;
use App\Services\PrivatePortraitStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CriancaController extends Controller
{
    public function __construct(
        private CreateCrianca $createCrianca,
        private UpdateCrianca $updateCrianca,
        private StorePrivatePortrait $storePrivatePortrait,
        private PrivatePortraitStorage $privatePortraits,
        private AuditRecorder $audit,
        private AcolhimentoProjection $acolhimentoProjection,
        private CriancaSituacaoQuery $situacaoQuery,
        private CriancaInformacaoEscolarHistory $schoolInformationHistory,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $this->authorize('viewAny', Crianca::class);

        $rawSituation = $request->query('situacao', 'todos');
        $situacao = is_string($rawSituation)
            ? CriancaSituacaoFiltro::tryFrom($rawSituation)
            : null;

        if ($situacao === null) {
            return redirect()->route('criancas.index', status: 303);
        }

        if ($request->query->has('q')) {
            $parameters = $request->query->has('situacao')
                ? ['situacao' => $situacao->value]
                : [];

            return redirect()->route('criancas.index', $parameters, 303);
        }

        $scopedChildren = $this->situacaoQuery->scopedChildren();
        $counts = $this->situacaoQuery->counts($scopedChildren);
        $query = $this->situacaoQuery->filter($scopedChildren, $situacao)
            ->orderBy('nome_completo')
            ->orderBy('id')
            ->select([
                'id', 'nome_completo', 'data_nascimento', 'processo_numero', 'foto',
                'data_acolhimento', 'motivo_acolhimento', 'status',
            ]);
        $this->situacaoQuery->addProjection($query);

        $criancas = $query
            ->paginate(12)
            ->appends(['situacao' => $situacao->value])
            ->through(function (Crianca $crianca): array {
                $this->withPortraitUrl($crianca);

                return [
                    'id' => $crianca->id,
                    'nome_completo' => $crianca->nome_completo,
                    'data_nascimento' => $crianca->data_nascimento?->toDateString(),
                    'processo_numero' => $crianca->processo_numero,
                    'foto_url' => $crianca->getAttribute('foto_url'),
                    ...$this->situacaoQuery->summary($crianca),
                ];
            });

        $filtrosSituacao = array_map(
            fn (array $option): array => [
                ...$option,
                'href' => route('criancas.index', ['situacao' => $option['value']]),
            ],
            $this->situacaoQuery->options($counts),
        );

        return Inertia::render('Criancas/Index', [
            'criancas' => $criancas,
            'situacao' => $situacao->value,
            'filtrosSituacao' => $filtrosSituacao,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Crianca::class);

        return Inertia::render('Criancas/Form', [
            'crianca' => null,
            'opcoesInformacaoEscolar' => $this->schoolInformationOptions(),
        ]);
    }

    public function store(UpsertCriancaRequest $request)
    {
        $this->authorize('create', Crianca::class);

        $validated = $request->validated();
        $schoolInformation = $validated['informacao_escolar'] ?? null;
        unset($validated['foto'], $validated['informacao_escolar']);
        $dados = $validated;
        $storedPhoto = null;

        if ($request->hasFile('foto')) {
            $storedPhoto = $this->storePrivatePortrait->handle($request->file('foto'));
            $dados['foto'] = $storedPhoto;
        }

        try {
            $crianca = $this->createCrianca->handle($dados, $request->user(), $schoolInformation);
        } catch (Throwable $exception) {
            $this->privatePortraits->deleteFailedWrite($storedPhoto);

            throw $exception;
        }

        return redirect()->route('criancas.show', $crianca)
            ->with('sucesso', 'Cadastro criado com sucesso.');
    }

    public function show(Request $request, Crianca $crianca)
    {
        $this->authorize('view', $crianca);

        $crianca->load([
            'criador',
            'familiares',
            'pias' => fn ($q) => $q->with('criador')->latest(),
            'visitasTecnicas' => fn ($q) => $q->with('criador')->latest('data_visita'),
            'reports' => fn ($q) => $q->with('criador')->latest(),
            'pertences' => fn ($q) => $q->with('criador')->latest(),
        ]);

        $acolhimentos = $crianca->acolhimentos()
            ->with(['criador:id,name', 'movimentacoes.criador:id,name'])
            ->get();
        $currentEpisode = $acolhimentos->last();
        $currentMovement = $currentEpisode?->movimentacoes->last();
        $currentSchoolInformation = $this->schoolInformationHistory->current($crianca)?->load('criador:id,name');
        $schoolHistory = $this->schoolInformationHistory->initialPage($crianca, $currentSchoolInformation);

        $this->audit->record('crianca.viewed', 'success', $request->user(), $crianca);

        $lastChange = $crianca->updated_by === null
            ? null
            : $crianca->loadMissing('atualizador')->atualizador;

        $this->withPortraitUrl($crianca);
        $crianca->makeHidden(['data_acolhimento', 'motivo_acolhimento', 'status']);

        return Inertia::render('Criancas/Show', [
            'crianca' => $crianca,
            'identificacao' => $crianca->identificacao($currentEpisode),
            'ultimaAtualizacao' => $lastChange === null ? null : [
                'author' => $lastChange->name,
                'at' => $crianca->updated_at,
            ],
            'acolhimento' => $currentEpisode === null ? null : [
                'id' => $currentEpisode->id,
                'situacao' => $currentMovement?->situacao_resultante?->value,
                'desde' => $currentMovement?->efetiva_em,
                'ingresso_em' => $currentEpisode->ingresso_em,
                'motivo' => $currentEpisode->motivo,
                'fundamento' => $currentEpisode->fundamento,
                'origem' => Acolhimento::ORIGENS[$currentEpisode->origem_codigo] ?? $currentEpisode->origem_codigo,
                'origem_complemento' => $currentEpisode->origem_complemento,
                'orgao_condutor' => Acolhimento::ORGAOS_CONDUTORES[$currentEpisode->orgao_condutor_codigo]
                    ?? $currentEpisode->orgao_condutor_codigo,
                'orgao_condutor_complemento' => $currentEpisode->orgao_condutor_complemento,
                'pessoa_condutora' => $currentEpisode->pessoa_condutora,
                'registrado_por' => $currentMovement?->criador?->name ?? $currentEpisode->criador?->name,
                'registrado_em' => $currentMovement?->recorded_at ?? $currentEpisode->recorded_at,
                'aberto' => $currentEpisode->encerrado_em === null,
            ],
            'linhaDoTempoAcolhimento' => $acolhimentos->flatMap(
                fn (Acolhimento $episode, int $episodeIndex) => $episode->movimentacoes->map(
                    fn ($movement): array => [
                        'id' => $movement->id,
                        'episodio_id' => $episode->id,
                        'episodio_ordem' => $episodeIndex + 1,
                        'tipo' => $movement->tipo->value,
                        'situacao' => $movement->situacao_resultante->value,
                        'efetiva_em' => $movement->efetiva_em,
                        'motivo' => $movement->motivo,
                        'fundamento' => $movement->fundamento,
                        'local_destino' => $movement->local_destino,
                        'observacao' => $movement->observacao,
                        'registrado_por' => $movement->criador?->name,
                        'registrado_em' => $movement->recorded_at,
                        'episodio_contexto' => $movement->tipo->value === 'ingresso' ? [
                            'motivo' => $episode->motivo,
                            'fundamento' => $episode->fundamento,
                            'origem' => Acolhimento::ORIGENS[$episode->origem_codigo] ?? $episode->origem_codigo,
                            'origem_complemento' => $episode->origem_complemento,
                            'orgao_condutor' => Acolhimento::ORGAOS_CONDUTORES[$episode->orgao_condutor_codigo]
                                ?? $episode->orgao_condutor_codigo,
                            'orgao_condutor_complemento' => $episode->orgao_condutor_complemento,
                            'pessoa_condutora' => $episode->pessoa_condutora,
                        ] : null,
                    ],
                ),
            )->values(),
            'legadoAConferir' => $this->acolhimentoProjection->legacyData($crianca),
            'opcoesAcolhimento' => [
                'origens' => Acolhimento::ORIGENS,
                'orgaos_condutores' => Acolhimento::ORGAOS_CONDUTORES,
            ],
            'informacaoEscolarAtual' => $currentSchoolInformation === null
                ? null
                : $this->schoolInformationHistory->serialize($currentSchoolInformation),
            'historicoInformacaoEscolar' => $schoolHistory,
            'opcoesInformacaoEscolar' => $this->schoolInformationOptions(),
        ]);
    }

    public function edit(Crianca $crianca)
    {
        $this->authorize('update', $crianca);

        $this->withPortraitUrl($crianca);

        return Inertia::render('Criancas/Form', [
            'crianca' => $crianca,
            'opcoesInformacaoEscolar' => $this->schoolInformationOptions(),
        ]);
    }

    public function update(UpsertCriancaRequest $request, Crianca $crianca)
    {
        $this->authorize('update', $crianca);

        $dados = $request->safe()->except('foto');
        $storedPhoto = null;

        if ($request->hasFile('foto')) {
            $storedPhoto = $this->storePrivatePortrait->handle($request->file('foto'));
            $dados['foto'] = $storedPhoto;
        }

        try {
            $this->updateCrianca->handle($crianca, $dados, $request->user());
        } catch (Throwable $exception) {
            $this->privatePortraits->deleteFailedWrite($storedPhoto);

            throw $exception;
        }

        return redirect()->route('criancas.show', $crianca)
            ->with('sucesso', 'Cadastro atualizado com sucesso.');
    }

    public function destroy(Request $request, Crianca $crianca)
    {
        $this->authorize('delete', $crianca);

        abort(405, 'Cadastros assistenciais não podem ser excluídos fisicamente.');
    }

    public function storeDocumento(Request $request, Crianca $crianca)
    {
        $this->authorize('update', $crianca);
        $this->authorize('create', CriancaDocumento::class);

        abort(423, 'O envio de anexos da ficha está temporariamente desativado.');
    }

    public function destroyDocumento(Request $request, CriancaDocumento $documento)
    {
        $this->authorize('delete', $documento);

        abort(405, 'Anexos assistenciais não podem ser excluídos fisicamente.');
    }

    public function storeFamiliar(Request $request, Crianca $crianca)
    {
        $this->authorize('update', $crianca);
        $this->authorize('create', Familiar::class);

        $dados = $request->validate([
            'tipo' => ['required', 'in:genitora,genitor,responsavel,familiar'],
            'nome' => ['required', 'string', 'max:255'],
            'parentesco' => ['nullable', 'string', 'max:100'],
            'data_nascimento' => ['nullable', 'date'],
            'rg' => ['nullable', 'string', 'max:50'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'ocupacao' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
        ]);
        $dados['created_by'] = $request->user()->id;

        $crianca->familiares()->create($dados);

        return back()->with('sucesso', 'Familiar cadastrado com sucesso.');
    }

    public function destroyFamiliar(Familiar $familiar)
    {
        $this->authorize('delete', $familiar);

        abort(405, 'Vínculos familiares não podem ser excluídos fisicamente.');
    }

    public function portrait(Request $request, Crianca $crianca)
    {
        if ($request->user()->cannot('viewPortrait', $crianca)) {
            $this->audit->record('access.denied.child_portrait.view', 'denied', $request->user());
            $request->attributes->set('_access_denial_audited', true);

            abort(403);
        }

        $portrait = $this->privatePortraits->read($crianca->foto);
        abort_if($portrait === null, 404);

        $this->audit->record('child_portrait.view', 'success', $request->user(), $crianca);

        return response($portrait['contents'], 200, [
            'Content-Type' => $portrait['mime'],
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function withPortraitUrl(Crianca $crianca): void
    {
        $crianca->setAttribute(
            'foto_url',
            $this->privatePortraits->hasValid($crianca->foto)
                ? route('criancas.portrait', $crianca)
                : null,
        );
    }

    /** @return array<string, array<string, string>> */
    private function schoolInformationOptions(): array
    {
        return [
            'situacoes' => CriancaInformacaoEscolar::SITUACOES,
            'redes' => CriancaInformacaoEscolar::REDES,
            'turnos' => CriancaInformacaoEscolar::TURNOS,
            'fontes' => CriancaInformacaoEscolar::FONTES,
        ];
    }
}
