<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertEventoRequest;
use App\Models\Evento;
use App\Services\AcolhimentoProjection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EventoController extends Controller
{
    public function __construct(private AcolhimentoProjection $acolhimentoProjection) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Evento::class);

        $eventos = Evento::with('crianca:id,nome_completo', 'criador:id,name', 'setor:id,nome')
            ->orderBy('inicio')
            ->get();

        $criancas = $this->acolhimentoProjection->childrenForSelection();

        return Inertia::render('Agenda/Index', [
            'eventos' => $eventos,
            'criancas' => $criancas,
            'tipos' => Evento::TIPOS,
        ]);
    }

    public function store(UpsertEventoRequest $request): RedirectResponse
    {
        $this->authorize('create', Evento::class);

        $dados = $request->validatedForPersistence();
        $dados['created_by'] = $request->user()->id;
        $dados['setor_id'] = $request->user()->setor_id;

        Evento::create($dados);

        return redirect()->route('agenda.index')
            ->with('sucesso', 'Compromisso agendado com sucesso.');
    }

    public function update(UpsertEventoRequest $request, Evento $evento): RedirectResponse
    {
        $this->authorize('update', $evento);

        $evento->update($request->validatedForPersistence());

        return redirect()->route('agenda.index')
            ->with('sucesso', 'Compromisso atualizado com sucesso.');
    }

    public function toggleConcluido(Evento $evento): RedirectResponse
    {
        $this->authorize('update', $evento);

        $evento->update(['concluido' => ! $evento->concluido]);

        return redirect()->route('agenda.index');
    }

    public function destroy(Evento $evento): RedirectResponse
    {
        $this->authorize('delete', $evento);

        abort(405, 'Compromissos devem ser cancelados e não excluídos fisicamente.');
    }
}
