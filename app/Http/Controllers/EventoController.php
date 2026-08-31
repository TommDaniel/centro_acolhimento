<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutorizaDocumento;
use App\Http\Requests\UpsertEventoRequest;
use App\Models\Crianca;
use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EventoController extends Controller
{
    use AutorizaDocumento;

    public function index(): Response
    {
        $eventos = Evento::with('crianca:id,nome_completo', 'criador:id,name', 'setor:id,nome')
            ->orderBy('inicio')
            ->get();

        $criancas = Crianca::where('status', 'acolhida')->orderBy('nome_completo')
            ->get(['id', 'nome_completo']);

        return Inertia::render('Agenda/Index', [
            'eventos' => $eventos,
            'criancas' => $criancas,
            'tipos' => Evento::TIPOS,
        ]);
    }

    public function store(UpsertEventoRequest $request): RedirectResponse
    {
        $dados = $request->validatedForPersistence();
        $dados['created_by'] = $request->user()->id;
        $dados['setor_id'] = $request->user()->setor_id;

        Evento::create($dados);

        return redirect()->route('agenda.index')
            ->with('sucesso', 'Compromisso agendado com sucesso.');
    }

    public function update(UpsertEventoRequest $request, Evento $evento): RedirectResponse
    {
        $this->autorizarDocumento($evento);

        $evento->update($request->validatedForPersistence());

        return redirect()->route('agenda.index')
            ->with('sucesso', 'Compromisso atualizado com sucesso.');
    }

    public function toggleConcluido(Evento $evento): RedirectResponse
    {
        $this->autorizarDocumento($evento);

        $evento->update(['concluido' => ! $evento->concluido]);

        return redirect()->route('agenda.index');
    }

    public function destroy(Evento $evento): RedirectResponse
    {
        $this->autorizarDocumento($evento);

        $evento->delete();

        return redirect()->route('agenda.index')
            ->with('sucesso', 'Compromisso removido.');
    }
}
