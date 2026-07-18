<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutorizaDocumento;
use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\Crianca;
use App\Models\Pertence;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PertenceController extends Controller
{
    use AutorizaDocumento;
    use EmiteOficio;

    public function index()
    {
        $pertences = Pertence::with('crianca', 'criador')->latest()->paginate(15);

        return Inertia::render('Pertences/Index', compact('pertences'));
    }

    public function create(Request $request)
    {
        $criancas = Crianca::where('status', 'acolhida')->orderBy('nome_completo')->get(['id', 'nome_completo']);

        return Inertia::render('Pertences/Form', [
            'pertence' => null,
            'criancas' => $criancas,
            'criancaId' => (int) $request->input('crianca_id') ?: null,
            'hoje' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        $dados['created_by'] = $request->user()->id;
        $dados['setor_id'] = $request->user()->setor_id;
        $dados['numero_oficio'] = $this->numeroOficio($request, 'pertences');
        $dados['itens'] = $this->montarItens($request);

        $pertence = Pertence::create($dados);

        return redirect()->route('pertences.show', $pertence)
            ->with('sucesso', 'Termo de pertences registrado com sucesso.');
    }

    public function show(Pertence $pertence)
    {
        $pertence->load('crianca', 'criador', 'setor');

        return Inertia::render('Pertences/Show', [
            'pertence' => $pertence,
            'identificacao' => $pertence->crianca->identificacao(),
        ]);
    }

    public function edit(Pertence $pertence)
    {
        $this->autorizarDocumento($pertence);

        $criancas = Crianca::orderBy('nome_completo')->get(['id', 'nome_completo']);

        return Inertia::render('Pertences/Form', [
            'pertence' => $pertence,
            'criancas' => $criancas,
            'criancaId' => $pertence->crianca_id,
            'hoje' => null,
        ]);
    }

    public function update(Request $request, Pertence $pertence)
    {
        $this->autorizarDocumento($pertence);

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
        $this->autorizarDocumento($pertence);

        $criancaId = $pertence->crianca_id;
        $pertence->delete();

        return redirect()->route('criancas.show', $criancaId)
            ->with('sucesso', 'Termo de pertences removido.');
    }

    public function pdf(Pertence $pertence)
    {
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
