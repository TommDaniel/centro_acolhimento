<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutorizaDocumento;
use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\Crianca;
use App\Models\VisitaTecnica;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class VisitaTecnicaController extends Controller
{
    use AutorizaDocumento;
    use EmiteOficio;

    public function index()
    {
        $visitas = VisitaTecnica::with('crianca', 'criador')->latest('data_visita')->paginate(15);

        return Inertia::render('Visitas/Index', compact('visitas'));
    }

    public function create(Request $request)
    {
        $criancas = Crianca::where('status', 'acolhida')->orderBy('nome_completo')->get(['id', 'nome_completo']);

        return Inertia::render('Visitas/Form', [
            'visita' => null,
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
        $dados['numero_oficio'] = $this->numeroOficio($request, 'visitas_tecnicas');

        $visita = VisitaTecnica::create($dados);

        return redirect()->route('visitas-tecnicas.show', $visita)
            ->with('sucesso', 'Visita técnica registrada com sucesso.');
    }

    public function show(VisitaTecnica $visitasTecnica)
    {
        $visita = $visitasTecnica->load('crianca', 'criador', 'setor');

        return Inertia::render('Visitas/Show', [
            'visita' => $visita,
            'secoes' => $visita->secoes(),
            'identificacao' => $visita->crianca->identificacao(),
        ]);
    }

    public function edit(VisitaTecnica $visitasTecnica)
    {
        $this->autorizarDocumento($visitasTecnica);

        $criancas = Crianca::orderBy('nome_completo')->get(['id', 'nome_completo']);

        return Inertia::render('Visitas/Form', [
            'visita' => $visitasTecnica,
            'criancas' => $criancas,
            'criancaId' => $visitasTecnica->crianca_id,
            'hoje' => null,
        ]);
    }

    public function update(Request $request, VisitaTecnica $visitasTecnica)
    {
        $this->autorizarDocumento($visitasTecnica);

        $visitasTecnica->update($this->validar($request));

        return redirect()->route('visitas-tecnicas.show', $visitasTecnica)
            ->with('sucesso', 'Visita técnica atualizada com sucesso.');
    }

    public function destroy(VisitaTecnica $visitasTecnica)
    {
        $this->autorizarDocumento($visitasTecnica);

        $criancaId = $visitasTecnica->crianca_id;
        $visitasTecnica->delete();

        return redirect()->route('criancas.show', $criancaId)
            ->with('sucesso', 'Visita técnica removida.');
    }

    public function pdf(VisitaTecnica $visitasTecnica)
    {
        $visita = $visitasTecnica->load('crianca', 'criador', 'setor');

        $arquivo = 'visita-tecnica-'.Str::slug($visita->crianca->nome_completo).'-'.$visita->data_visita->format('Ymd').'.pdf';

        return Pdf::loadView('pdf.visita', [
            'visita' => $visita,
            'local_oficio' => self::LOCAL_OFICIO,
            'data_extenso' => dataPorExtensoPtBr($visita->created_at),
        ])
            ->setPaper('a4')
            ->stream($arquivo);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'crianca_id' => ['required', 'exists:criancas,id'],
            'numero_oficio' => $this->regraNumeroOficio(),
            'data_visita' => ['required', 'date'],
            'hora_visita' => ['nullable', 'date_format:H:i'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'visitante' => ['nullable', 'string', 'max:255'],
            'local' => ['nullable', 'string', 'max:255'],
            'motivo' => ['nullable', 'string'],
            'relato' => ['required', 'string'],
            'encaminhamentos' => ['nullable', 'string'],
        ]);
    }
}
