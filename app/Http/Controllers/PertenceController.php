<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\Pertence;
use App\Services\AcolhimentoProjection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PertenceController extends Controller
{
    use EmiteOficio;

    public function __construct(private AcolhimentoProjection $acolhimentoProjection) {}

    public function index()
    {
        $this->authorize('viewAny', Pertence::class);

        $pertences = Pertence::with('crianca', 'criador')->latest()->paginate(15);

        return Inertia::render('Pertences/Index', compact('pertences'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Pertence::class);

        $criancas = $this->acolhimentoProjection->childrenForSelection();

        return Inertia::render('Pertences/Form', [
            'pertence' => null,
            'criancas' => $criancas,
            'criancaId' => (int) $request->input('crianca_id') ?: null,
            'hoje' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Pertence::class);

        $dados = $this->validar($request);
        $dados['setor_id'] = $request->user()->setor_id;
        $dados['numero_oficio'] = $this->numeroOficio($request, 'pertences');
        $dados['itens'] = $this->montarItens($request);

        $pertence = new Pertence($dados);
        $pertence->created_by = $request->user()->id;
        $pertence->save();

        return redirect()->route('pertences.show', $pertence)
            ->with('sucesso', 'Termo de pertences registrado com sucesso.');
    }

    public function show(Pertence $pertence)
    {
        $this->authorize('view', $pertence);

        $pertence->load('crianca', 'criador', 'setor');

        return Inertia::render('Pertences/Show', [
            'pertence' => $pertence,
            'identificacao' => $pertence->crianca->identificacao(),
        ]);
    }

    public function edit(Pertence $pertence)
    {
        $this->authorize('update', $pertence);

        $criancas = $this->acolhimentoProjection->childrenForSelection();

        return Inertia::render('Pertences/Form', [
            'pertence' => $pertence,
            'criancas' => $criancas,
            'criancaId' => $pertence->crianca_id,
            'hoje' => null,
        ]);
    }

    public function update(Request $request, Pertence $pertence)
    {
        $this->authorize('update', $pertence);

        $dados = $this->validar($request, true);
        if ($request->has('itens')) {
            $dados['itens'] = $this->montarItens($request);
        }
        $dados['devolvido'] = $request->boolean('devolvido');

        $pertence->update($dados);

        return redirect()->route('pertences.show', $pertence)
            ->with('sucesso', 'Termo de pertences atualizado com sucesso.');
    }

    public function destroy(Pertence $pertence)
    {
        $this->authorize('delete', $pertence);

        abort(405, 'Documentos assistenciais não podem ser excluídos fisicamente.');
    }

    public function pdf(Pertence $pertence)
    {
        $this->authorize('download', $pertence);

        $pertence->load('crianca', 'criador', 'setor');

        $arquivo = 'pertences-'.Str::slug($pertence->crianca->nome_completo).'-'.$pertence->data_entrega->format('Ymd').'.pdf';

        return Pdf::loadView('pdf.pertence', [
            'pertence' => $pertence,
            'local_oficio' => self::LOCAL_OFICIO,
            'data_extenso' => dataPorExtensoPtBr($pertence->created_at),
        ])
            ->setPaper('a4')
            ->stream($arquivo);
    }

    /**
     * @return array<int, array{descricao: string, quantidade: string|null}>
     */
    private function montarItens(Request $request): array
    {
        return collect($request->input('itens', []))
            ->map(fn ($item) => [
                'descricao' => trim((string) ($item['descricao'] ?? '')),
                'quantidade' => $item['quantidade'] ?? null,
            ])
            ->filter(fn ($item) => $item['descricao'] !== '')
            ->values()
            ->all();
    }

    private function validar(Request $request, bool $edicao = false): array
    {
        return $request->validate([
            'crianca_id' => [$edicao ? 'sometimes' : 'required', 'exists:criancas,id'],
            'numero_oficio' => $this->regraNumeroOficio(),
            'itens' => [$edicao ? 'sometimes' : 'required', 'array', 'min:1'],
            'itens.*.descricao' => ['nullable', 'string', 'max:255'],
            'itens.*.quantidade' => ['nullable', 'string', 'max:50'],
            'data_entrega' => ['required', 'date'],
            'assinatura_entrega' => ['nullable', 'string', 'max:255'],
            'devolvido' => ['nullable', 'boolean'],
            'data_devolucao' => ['nullable', 'date'],
            'assinatura_devolucao' => ['nullable', 'string', 'max:255'],
            'observacao_devolucao' => ['nullable', 'string'],
        ]);
    }
}
