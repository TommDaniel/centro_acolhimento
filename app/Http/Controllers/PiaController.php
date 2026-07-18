<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutorizaDocumento;
use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\Crianca;
use App\Models\Pia;
use App\Models\PiaAnexo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PiaController extends Controller
{
    use AutorizaDocumento;
    use EmiteOficio;

    public function index()
    {
        $pias = Pia::with('crianca', 'criador')->latest()->paginate(15);

        return Inertia::render('Pias/Index', compact('pias'));
    }

    public function create(Request $request)
    {
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

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        $dados['created_by'] = $request->user()->id;
        $dados['setor_id'] = $request->user()->setor_id;
        $dados['numero_oficio'] = $this->numeroOficio($request, 'pias');

        $pia = Pia::create($dados);
        $this->processarAnexos($request, $pia);

        return redirect()->route('pias.show', $pia)
            ->with('sucesso', 'PIA registrado com sucesso.');
    }

    public function show(Pia $pia)
    {
        $pia->load('crianca.familiares', 'criador', 'setor', 'anexos.uploader');

        return Inertia::render('Pias/Show', [
            'pia' => $pia,
            'secoes' => $pia->secoes(),
            'identificacao' => $pia->crianca->identificacao(),
            'familiares' => $pia->crianca->familiares,
        ]);
    }

    public function edit(Pia $pia)
    {
        $this->autorizarDocumento($pia);

        $criancas = Crianca::orderBy('nome_completo')
            ->get(['id', 'nome_completo', 'data_acolhimento', 'motivo_acolhimento', 'processo_numero', 'vara', 'comarca']);

        return Inertia::render('Pias/Form', [
            'pia' => $pia,
            'criancas' => $criancas,
            'criancaId' => $pia->crianca_id,
        ]);
    }

    public function update(Request $request, Pia $pia)
    {
        $this->autorizarDocumento($pia);

        $pia->update($this->validar($request));
        $this->processarAnexos($request, $pia);

        return redirect()->route('pias.show', $pia)
            ->with('sucesso', 'PIA atualizado com sucesso.');
    }

    public function destroy(Pia $pia)
    {
        $this->autorizarDocumento($pia);

        $criancaId = $pia->crianca_id;

        foreach ($pia->anexos as $anexo) {
            Storage::disk('public')->delete($anexo->path);
        }

        $pia->delete();

        return redirect()->route('criancas.show', $criancaId)
            ->with('sucesso', 'PIA removido.');
    }

    public function destroyAnexo(Request $request, PiaAnexo $anexo)
    {
        $this->autorizarDocumento($anexo->pia);

        abort_unless(
            $request->user()->is_admin || $anexo->uploaded_by === $request->user()->id,
            403,
            'Somente o autor do envio ou um administrador pode remover o anexo.'
        );

        Storage::disk('public')->delete($anexo->path);
        $anexo->delete();

        return back()->with('sucesso', 'Anexo removido.');
    }

    public function pdf(Pia $pia)
    {
        $pia->load('crianca.familiares', 'criador', 'setor', 'anexos');

        $arquivo = 'pia-'.Str::slug($pia->crianca->nome_completo).'-'.$pia->created_at->format('Ymd').'.pdf';

        return Pdf::loadView('pdf.pia', [
            'pia' => $pia,
            'local_oficio' => self::LOCAL_OFICIO,
            'data_extenso' => dataPorExtensoPtBr($pia->created_at),
        ])
            ->setPaper('a4')
            ->stream($arquivo);
    }

    private function processarAnexos(Request $request, Pia $pia): void
    {
        $request->validate([
            'anexos' => ['nullable', 'array', 'max:10'],
            'anexos.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
            'anexos_descricao' => ['nullable', 'array'],
            'anexos_descricao.*' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($request->file('anexos', []) as $indice => $arquivo) {
            $pia->anexos()->create([
                'nome_original' => $arquivo->getClientOriginalName(),
                'descricao' => $request->input("anexos_descricao.$indice"),
                'path' => $arquivo->store('anexos/pias/'.$pia->id, 'public'),
                'mime' => $arquivo->getClientMimeType(),
                'tamanho' => $arquivo->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'crianca_id' => ['required', 'exists:criancas,id'],
            'numero_oficio' => $this->regraNumeroOficio(),
            'composicao_familiar' => ['nullable', 'string'],
            'dados_acolhimento' => ['nullable', 'string'],
            'acolhimento_anterior' => ['nullable', 'boolean'],
            'acolhimento_anterior_detalhes' => ['nullable', 'string', 'required_if:acolhimento_anterior,1'],
            'encaminhado_por' => ['nullable', 'string', 'max:255'],
            'especificidades' => ['nullable', 'string'],
            'informacoes_familia' => ['nullable', 'string'],
            'saude' => ['nullable', 'string'],
            'saude_familiares' => ['nullable', 'string'],
            'educacao_menor' => ['nullable', 'string'],
            'educacao_familiares' => ['nullable', 'string'],
            'assistencia_social' => ['nullable', 'string'],
            'assistencia_social_familiares' => ['nullable', 'string'],
            'esporte_cultura_lazer' => ['nullable', 'string'],
            'consideracoes_tecnicas' => ['nullable', 'string'],
            'plano_acao' => ['nullable', 'string'],
            'providencias_judiciario' => ['nullable', 'string'],
        ]);
    }
}
