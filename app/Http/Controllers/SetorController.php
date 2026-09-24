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
        $this->authorize('viewAny', Setor::class);

        $setores = Setor::withCount('users')->orderBy('nome')->get();

        return Inertia::render('Setores/Index', compact('setores'));
    }

    public function show(Setor $setor)
    {
        $this->authorize('view', $setor);

        $setor->load(['users' => fn ($query) => $query
            ->select(['id', 'name', 'cargo', 'setor_id'])
            ->orderBy('name')]);
        $setor->users->each->setAppends([]);

        // Subtópicos do setor: documentos produzidos pela equipe, por tipo.
        $subtopicos = [
            'pias' => Pia::query()
                ->select(['id', 'crianca_id', 'created_by', 'setor_id', 'created_at'])
                ->with('crianca:id,nome_completo', 'criador:id,name')
                ->where('setor_id', $setor->id)->latest()->take(10)->get(),
            'reports' => Report::query()
                ->select(['id', 'crianca_id', 'created_by', 'setor_id', 'created_at'])
                ->with('crianca:id,nome_completo', 'criador:id,name')
                ->where('setor_id', $setor->id)->latest()->take(10)->get(),
            'visitas' => VisitaTecnica::query()
                ->select(['id', 'crianca_id', 'created_by', 'setor_id', 'data_visita'])
                ->with('crianca:id,nome_completo', 'criador:id,name')
                ->where('setor_id', $setor->id)->latest('data_visita')->take(10)->get(),
            'pertences' => Pertence::query()
                ->select(['id', 'crianca_id', 'created_by', 'setor_id', 'created_at'])
                ->with('crianca:id,nome_completo', 'criador:id,name')
                ->where('setor_id', $setor->id)->latest()->take(10)->get(),
        ];

        collect($subtopicos)->each(fn ($documents) => $documents->each(function ($document): void {
            $document->crianca?->setAppends([]);
            $document->criador?->setAppends([]);
        }));

        return Inertia::render('Setores/Show', compact('setor', 'subtopicos'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Setor::class);

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
        $this->authorize('update', $setor);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:setores,nome,'.$setor->id],
            'descricao' => ['nullable', 'string'],
        ]);

        $setor->update($dados);

        return back()->with('sucesso', 'Setor atualizado com sucesso.');
    }

    public function destroy(Setor $setor)
    {
        $this->authorize('delete', $setor);

        abort(405, 'Setores vinculados ao histórico não podem ser excluídos fisicamente.');
    }
}
