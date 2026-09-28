<?php

namespace App\Http\Controllers;

use App\Actions\CreateCrianca;
use App\Actions\StorePrivatePortrait;
use App\Actions\UpdateCrianca;
use App\Http\Requests\UpsertCriancaRequest;
use App\Models\Crianca;
use App\Models\CriancaDocumento;
use App\Models\Familiar;
use App\Services\AuditRecorder;
use App\Services\PrivatePortraitStorage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class CriancaController extends Controller
{
    public function __construct(
        private CreateCrianca $createCrianca,
        private UpdateCrianca $updateCrianca,
        private StorePrivatePortrait $storePrivatePortrait,
        private PrivatePortraitStorage $privatePortraits,
        private AuditRecorder $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Crianca::class);

        $q = trim((string) $request->input('q'));
        $status = $request->input('status', 'acolhida');

        $criancas = Crianca::query()
            ->when($status !== 'todas', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($where) use ($like) {
                    $where->where('nome_completo', 'like', $like)
                        ->orWhere('nome_social', 'like', $like)
                        ->orWhere('processo_numero', 'like', $like)
                        ->orWhere('rg', 'like', $like);
                });
            })
            ->orderBy('nome_completo')
            ->paginate(12)
            ->withQueryString();

        $criancas->getCollection()->each(fn (Crianca $crianca) => $this->withPortraitUrl($crianca));

        return Inertia::render('Criancas/Index', compact('criancas', 'q', 'status'));
    }

    public function create()
    {
        $this->authorize('create', Crianca::class);

        return Inertia::render('Criancas/Form', ['crianca' => null]);
    }

    public function store(UpsertCriancaRequest $request)
    {
        $this->authorize('create', Crianca::class);

        $dados = $request->safe()->except('foto');
        $storedPhoto = null;

        if ($request->hasFile('foto')) {
            $storedPhoto = $this->storePrivatePortrait->handle($request->file('foto'));
            $dados['foto'] = $storedPhoto;
        }

        try {
            $crianca = $this->createCrianca->handle($dados, $request->user());
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

        $this->audit->record('crianca.viewed', 'success', $request->user(), $crianca);

        $lastChange = $crianca->updated_by === null
            ? null
            : $crianca->loadMissing('atualizador')->atualizador;

        $this->withPortraitUrl($crianca);

        return Inertia::render('Criancas/Show', [
            'crianca' => $crianca,
            'identificacao' => $crianca->identificacao(),
            'ultimaAtualizacao' => $lastChange === null ? null : [
                'author' => $lastChange->name,
                'at' => $crianca->updated_at,
            ],
        ]);
    }

    public function edit(Crianca $crianca)
    {
        $this->authorize('update', $crianca);

        $this->withPortraitUrl($crianca);

        return Inertia::render('Criancas/Form', compact('crianca'));
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
}
