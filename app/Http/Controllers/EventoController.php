<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutorizaDocumento;
use App\Models\Crianca;
use App\Models\Evento;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventoController extends Controller
{
    use AutorizaDocumento;

    public function index()
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

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        $dados['created_by'] = $request->user()->id;
        $dados['setor_id'] = $request->user()->setor_id;

        Evento::create($dados);

        return redirect()->route('agenda.index')
            ->with('sucesso', 'Compromisso agendado com sucesso.');
    }

    public function update(Request $request, Evento $evento)
    {
        $this->autorizarDocumento($evento);

        $evento->update($this->validar($request));

        return redirect()->route('agenda.index')
            ->with('sucesso', 'Compromisso atualizado com sucesso.');
    }

    public function toggleConcluido(Evento $evento)
    {
        $this->autorizarDocumento($evento);

        $evento->update(['concluido' => ! $evento->concluido]);

        return redirect()->route('agenda.index');
    }

    public function destroy(Evento $evento)
    {
        $this->autorizarDocumento($evento);

        $evento->delete();

        return redirect()->route('agenda.index')
            ->with('sucesso', 'Compromisso removido.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:visita,audiencia,atendimento,tarefa,outro'],
            'descricao' => ['nullable', 'string'],
            'inicio' => ['required', 'date'],
            'fim' => ['nullable', 'date', 'after_or_equal:inicio'],
            'dia_inteiro' => ['nullable', 'boolean'],
            'crianca_id' => ['nullable', 'exists:criancas,id'],
        ]);
    }
}
