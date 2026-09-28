<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EmiteOficio;
use App\Http\Requests\UpsertPiaRequest;
use App\Models\Crianca;
use App\Models\Pia;
use App\Models\PiaAnexo;
use App\Services\AuditRecorder;
use App\Services\PrivatePortraitStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PiaController extends Controller
{
    use EmiteOficio;

    public function __construct(
        private PrivatePortraitStorage $privatePortraits,
        private AuditRecorder $audit,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Pia::class);

        $pias = Pia::with('crianca', 'criador')->latest()->paginate(15);

        return Inertia::render('Pias/Index', compact('pias'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Pia::class);

        // Campos necessários para o front pré-preencher os dados do acolhimento
        // assim que a criança é selecionada — sem redigitação.
        $criancas = Crianca::where('status', 'acolhida')->orderBy('nome_completo')
            ->get(['id', 'nome_completo', 'data_acolhimento', 'motivo_acolhimento', 'processo_numero', 'vara', 'comarca']);

        return Inertia::render('Pias/Form', [
            'pia' => null,
            'criancas' => $criancas,
            'criancaId' => (int) $request->input('crianca_id') ?: null,
        ]);
    }

    public function store(UpsertPiaRequest $request)
    {
        $this->authorize('create', Pia::class);

        $dados = $request->validated();
        $dados['created_by'] = $request->user()->id;
        $dados['setor_id'] = $request->user()->setor_id;
        $dados['numero_oficio'] = $this->numeroOficio($request, 'pias');

        $pia = Pia::create($dados);

        return redirect()->route('pias.show', $pia)
            ->with('sucesso', 'PIA registrado com sucesso.');
    }

    public function show(Pia $pia)
    {
        $this->authorize('view', $pia);

        $pia->load('crianca.familiares', 'criador', 'setor');
        $this->withPortraitUrl($pia->crianca);

        return Inertia::render('Pias/Show', [
            'pia' => $pia,
            'secoes' => $pia->secoes(),
            'identificacao' => $pia->crianca->identificacao(),
            'familiares' => $pia->crianca->familiares,
        ]);
    }

    public function edit(Pia $pia)
    {
        $this->authorize('update', $pia);

        $criancas = Crianca::orderBy('nome_completo')
            ->get(['id', 'nome_completo', 'data_acolhimento', 'motivo_acolhimento', 'processo_numero', 'vara', 'comarca']);

        return Inertia::render('Pias/Form', [
            'pia' => $pia,
            'criancas' => $criancas,
            'criancaId' => $pia->crianca_id,
        ]);
    }

    public function update(UpsertPiaRequest $request, Pia $pia)
    {
        $this->authorize('update', $pia);

        $pia->update($request->validated());

        return redirect()->route('pias.show', $pia)
            ->with('sucesso', 'PIA atualizado com sucesso.');
    }

    public function destroy(Pia $pia)
    {
        $this->authorize('delete', $pia);

        abort(405, 'Documentos assistenciais não podem ser excluídos fisicamente.');
    }

    public function destroyAnexo(Request $request, PiaAnexo $anexo)
    {
        $this->authorize('delete', $anexo);

        abort(405, 'Anexos assistenciais não podem ser excluídos fisicamente.');
    }

    public function pdf(Request $request, Pia $pia)
    {
        $this->authorize('download', $pia);

        $pia->load('crianca.familiares', 'criador', 'setor');
        $portrait = $this->privatePortraits->read($pia->crianca->foto);
        $portraitForPdf = $portrait === null ? null : $this->portraitForPdf($portrait);

        $arquivo = 'pia-'.$pia->getKey().'-'.$pia->created_at->format('Ymd').'.pdf';

        $response = Pdf::loadView('pdf.pia', [
            'pia' => $pia,
            'local_oficio' => self::LOCAL_OFICIO,
            'data_extenso' => dataPorExtensoPtBr($pia->created_at),
            'portrait' => $portraitForPdf,
        ])
            ->setPaper('a4')
            ->stream($arquivo);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        $this->audit->record('pia.pdf_view', 'success', $request->user(), $pia);

        return $response;
    }

    private function withPortraitUrl(Crianca $crianca): void
    {
        $crianca->setAttribute(
            'foto_url',
            $this->privatePortraits->hasValid($crianca->foto)
                ? route('criancas.portrait', $crianca)
                : null,
        );
    }

    /**
     * @param  array{contents: string, mime: 'image/jpeg'|'image/png', width: int, height: int}  $portrait
     * @return array{data_uri: string, width: float, height: float}
     */
    private function portraitForPdf(array $portrait): array
    {
        $scale = min(88 / $portrait['width'], 110 / $portrait['height']);

        return [
            'data_uri' => 'data:'.$portrait['mime'].';base64,'.base64_encode($portrait['contents']),
            'width' => round($portrait['width'] * $scale, 2),
            'height' => round($portrait['height'] * $scale, 2),
        ];
    }
}
