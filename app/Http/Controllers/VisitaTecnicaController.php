<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\VisitaTecnica;
use App\Services\AcolhimentoProjection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class VisitaTecnicaController extends Controller
{
    use EmiteOficio;

    public function __construct(private AcolhimentoProjection $acolhimentoProjection) {}

    public function index()
    {
        $this->authorize('viewAny', VisitaTecnica::class);

        $visitas = VisitaTecnica::with('crianca', 'criador')->latest('data_visita')->paginate(15);

        return Inertia::render('Visitas/Index', compact('visitas'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', VisitaTecnica::class);

        $criancas = $this->acolhimentoProjection->childrenForSelection();

        return Inertia::render('Visitas/Form', [
            'visita' => null,
            'criancas' => $criancas,
            'criancaId' => (int) $request->input('crianca_id') ?: null,
            'hoje' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', VisitaTecnica::class);

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
        $this->authorize('view', $visitasTecnica);

        $visita = $visitasTecnica->load('crianca', 'criador', 'setor');

        return Inertia::render('Visitas/Show', [
            'visita' => $visita,
            'secoes' => $visita->secoes(),
            'identificacao' => $visita->crianca->identificacao(),
        ]);
    }

    public function edit(VisitaTecnica $visitasTecnica)
    {
        $this->authorize('update', $visitasTecnica);

        $criancas = $this->acolhimentoProjection->childrenForSelection();

        return Inertia::render('Visitas/Form', [
            'visita' => $visitasTecnica,
            'criancas' => $criancas,
            'criancaId' => $visitasTecnica->crianca_id,
            'hoje' => null,
        ]);
    }

    public function update(Request $request, VisitaTecnica $visitasTecnica)
    {
        $this->authorize('update', $visitasTecnica);

        $visitasTecnica->update($this->validar($request));

        return redirect()->route('visitas-tecnicas.show', $visitasTecnica)
            ->with('sucesso', 'Visita técnica atualizada com sucesso.');
    }

    public function destroy(VisitaTecnica $visitasTecnica)
    {
        $this->authorize('delete', $visitasTecnica);

        abort(405, 'Documentos assistenciais não podem ser excluídos fisicamente.');
    }

    public function pdf(VisitaTecnica $visitasTecnica)
    {
        $this->authorize('download', $visitasTecnica);

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
