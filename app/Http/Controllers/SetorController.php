<?php

namespace App\Http\Controllers;

use App\Models\Pertence;
use App\Models\Pia;
use App\Models\Report;
use App\Models\Setor;
use App\Models\VisitaTecnica;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SetorController extends Controller
{
    public function index()
    {
        $setores = Setor::withCount('users')->orderBy('nome')->get();

        return Inertia::render('Setores/Index', compact('setores'));
    }

    public function show(Setor $setor)
    {
        $setor->load(['users' => fn ($q) => $q->orderBy('name')]);

        // Subtópicos do setor: documentos produzidos pela equipe, por tipo.
        $subtopicos = [
            'pias' => Pia::with('crianca', 'criador')->where('setor_id', $setor->id)->latest()->take(10)->get(),
            'reports' => Report::with('crianca', 'criador')->where('setor_id', $setor->id)->latest()->take(10)->get(),
            'visitas' => VisitaTecnica::with('crianca', 'criador')->where('setor_id', $setor->id)->latest('data_visita')->take(10)->get(),
            'pertences' => Pertence::with('crianca', 'criador')->where('setor_id', $setor->id)->latest()->take(10)->get(),
        ];

        return Inertia::render('Setores/Show', compact('setor', 'subtopicos'));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:setores,nome'],
            'descricao' => ['nullable', 'string'],
        ]);

        $setor = Setor::create($dados);

        return redirect()->route('setores.show', $setor)
            ->with('sucesso', 'Setor criado com sucesso.');
    }

    public function update(Request $request, Setor $setor)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:setores,nome,'.$setor->id],
            'descricao' => ['nullable', 'string'],
        ]);

        $setor->update($dados);

        return back()->with('sucesso', 'Setor atualizado com sucesso.');
    }

    public function destroy(Setor $setor)
    {
        $setor->delete();

        return redirect()->route('setores.index')
            ->with('sucesso', 'Setor removido.');
    }
}
