<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\Report;
use App\Services\AcolhimentoProjection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ReportController extends Controller
{
    use EmiteOficio;

    public function __construct(private AcolhimentoProjection $acolhimentoProjection) {}

    public function index()
    {
        $this->authorize('viewAny', Report::class);

        $reports = Report::with('crianca', 'criador')->latest()->paginate(15);

        return Inertia::render('Reports/Index', compact('reports'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Report::class);

        $criancas = $this->acolhimentoProjection->childrenForSelection();

        return Inertia::render('Reports/Form', [
            'report' => null,
            'criancas' => $criancas,
            'criancaId' => (int) $request->input('crianca_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Report::class);

        $dados = $this->validar($request);
        $dados['created_by'] = $request->user()->id;
        $dados['setor_id'] = $request->user()->setor_id;
        $dados['numero_oficio'] = $this->numeroOficio($request, 'reports');

        $report = Report::create($dados);

        return redirect()->route('reports.show', $report)
            ->with('sucesso', 'Relatório de ocorrência registrado com sucesso.');
    }

    public function show(Report $report)
    {
        $this->authorize('view', $report);

        $report->load('crianca', 'criador', 'setor');

        return Inertia::render('Reports/Show', [
            'report' => $report,
            'secoes' => $report->secoes(),
            'identificacao' => $report->crianca->identificacao(),
        ]);
    }

    public function edit(Report $report)
    {
        $this->authorize('update', $report);

        $criancas = $this->acolhimentoProjection->childrenForSelection();

        return Inertia::render('Reports/Form', [
            'report' => $report,
            'criancas' => $criancas,
            'criancaId' => $report->crianca_id,
        ]);
    }

    public function update(Request $request, Report $report)
    {
        $this->authorize('update', $report);

        $report->update($this->validar($request));

        return redirect()->route('reports.show', $report)
            ->with('sucesso', 'Relatório de ocorrência atualizado com sucesso.');
    }

    public function destroy(Report $report)
    {
        $this->authorize('delete', $report);

        abort(405, 'Documentos assistenciais não podem ser excluídos fisicamente.');
    }

    public function pdf(Report $report)
    {
        $this->authorize('download', $report);

        $report->load('crianca', 'criador', 'setor');

        $arquivo = 'ocorrencia-'.Str::slug($report->crianca->nome_completo).'-'.$report->created_at->format('Ymd').'.pdf';

        return Pdf::loadView('pdf.report', [
            'report' => $report,
            'local_oficio' => self::LOCAL_OFICIO,
            'data_extenso' => dataPorExtensoPtBr($report->created_at),
        ])
            ->setPaper('a4')
            ->stream($arquivo);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'crianca_id' => ['required', 'exists:criancas,id'],
            'numero_oficio' => $this->regraNumeroOficio(),
            'titulo' => ['nullable', 'string', 'max:255'],
            'introducao' => ['required', 'string'],
            'desenvolvimento' => ['required', 'string'],
            'consideracoes' => ['nullable', 'string'],
        ]);
    }
}
