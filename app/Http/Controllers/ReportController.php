<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutorizaDocumento;
use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\Crianca;
use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ReportController extends Controller
{
    use AutorizaDocumento;
    use EmiteOficio;

    public function index()
    {
        $reports = Report::with('crianca', 'criador')->latest()->paginate(15);

        return Inertia::render('Reports/Index', compact('reports'));
    }

    public function create(Request $request)
    {
        $criancas = Crianca::where('status', 'acolhida')->orderBy('nome_completo')->get(['id', 'nome_completo']);

        return Inertia::render('Reports/Form', [
            'report' => null,
            'criancas' => $criancas,
            'criancaId' => (int) $request->input('crianca_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
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
        $report->load('crianca', 'criador', 'setor');

        return Inertia::render('Reports/Show', [
            'report' => $report,
            'secoes' => $report->secoes(),
            'identificacao' => $report->crianca->identificacao(),
        ]);
    }

    public function edit(Report $report)
    {
        $this->autorizarDocumento($report);

        $criancas = Crianca::orderBy('nome_completo')->get(['id', 'nome_completo']);

        return Inertia::render('Reports/Form', [
            'report' => $report,
            'criancas' => $criancas,
            'criancaId' => $report->crianca_id,
        ]);
    }

    public function update(Request $request, Report $report)
    {
        $this->autorizarDocumento($report);

        $report->update($this->validar($request));

        return redirect()->route('reports.show', $report)
            ->with('sucesso', 'Relatório de ocorrência atualizado com sucesso.');
    }

    public function destroy(Report $report)
    {
        $this->autorizarDocumento($report);

        $criancaId = $report->crianca_id;
        $report->delete();

        return redirect()->route('criancas.show', $criancaId)
            ->with('sucesso', 'Relatório de ocorrência removido.');
    }

    public function pdf(Report $report)
    {
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
