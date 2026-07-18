<?php

namespace App\Http\Controllers;

use App\Models\Crianca;
use App\Models\CriancaDocumento;
use App\Models\Familiar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CriancaController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $status = $request->input('status', 'acolhida');

        $criancas = Crianca::query()
            ->when($status !== 'todas', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($where) use ($like) {
                    $where->where('nome_completo', 'like', $like)
                        ->orWhere('nome_social', 'like', $like)
                        ->orWhere('processo_numero', 'like', $like)
                        ->orWhere('rg', 'like', $like);
                });
            })
            ->orderBy('nome_completo')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Criancas/Index', compact('criancas', 'q', 'status'));
    }

    public function create()
    {
        return Inertia::render('Criancas/Form', ['crianca' => null]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        $dados['created_by'] = $request->user()->id;

        if ($request->hasFile('foto')) {
            $dados['foto'] = $request->file('foto')->store('fotos', 'public');
        }

        $crianca = Crianca::create($dados);

        return redirect()->route('criancas.show', $crianca)
            ->with('sucesso', 'Cadastro criado com sucesso.');
    }

    public function show(Crianca $crianca)
    {
        $crianca->load([
            'criador',
            'documentos.uploader',
            'familiares',
            'pias' => fn ($q) => $q->with('criador')->latest(),
            'visitasTecnicas' => fn ($q) => $q->with('criador')->latest('data_visita'),
            'reports' => fn ($q) => $q->with('criador')->latest(),
            'pertences' => fn ($q) => $q->with('criador')->latest(),
        ]);

        return Inertia::render('Criancas/Show', [
            'crianca' => $crianca,
            'identificacao' => $crianca->identificacao(),
        ]);
    }

    public function edit(Crianca $crianca)
    {
        return Inertia::render('Criancas/Form', compact('crianca'));
    }

    public function update(Request $request, Crianca $crianca)
    {
        $dados = $this->validar($request);
        $dados['updated_by'] = $request->user()->id;

        if ($request->hasFile('foto')) {
            if ($crianca->foto) {
                Storage::disk('public')->delete($crianca->foto);
            }
            $dados['foto'] = $request->file('foto')->store('fotos', 'public');
        }

        $crianca->update($dados);

        return redirect()->route('criancas.show', $crianca)
            ->with('sucesso', 'Cadastro atualizado com sucesso.');
    }

    public function destroy(Request $request, Crianca $crianca)
    {
        abort_unless($request->user()->is_admin, 403, 'Somente administradores podem remover cadastros.');

        if ($crianca->foto) {
            Storage::disk('public')->delete($crianca->foto);
        }
        foreach ($crianca->documentos as $documento) {
            Storage::disk('public')->delete($documento->path);
        }
        $crianca->delete();

        return redirect()->route('criancas.index')
            ->with('sucesso', 'Cadastro removido.');
    }

    public function storeDocumento(Request $request, Crianca $crianca)
    {
        $request->validate([
            'anexos' => ['required', 'array', 'max:10'],
            'anexos.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);

        foreach ($request->file('anexos', []) as $arquivo) {
            $crianca->documentos()->create([
                'nome_original' => $arquivo->getClientOriginalName(),
                'path' => $arquivo->store('documentos', 'public'),
                'mime' => $arquivo->getClientMimeType(),
                'tamanho' => $arquivo->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('sucesso', 'Documento(s) anexado(s) com sucesso.');
    }

    public function destroyDocumento(Request $request, CriancaDocumento $documento)
    {
        abort_unless(
            $request->user()->is_admin || $documento->uploaded_by === $request->user()->id,
            403,
            'Somente o autor do envio ou um administrador pode remover o anexo.'
        );

        Storage::disk('public')->delete($documento->path);
        $documento->delete();

        return back()->with('sucesso', 'Anexo removido.');
    }

    public function storeFamiliar(Request $request, Crianca $crianca)
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:genitora,genitor,responsavel,familiar'],
            'nome' => ['required', 'string', 'max:255'],
            'parentesco' => ['nullable', 'string', 'max:100'],
            'data_nascimento' => ['nullable', 'date'],
            'rg' => ['nullable', 'string', 'max:50'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'ocupacao' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
        ]);
        $dados['created_by'] = $request->user()->id;

        $crianca->familiares()->create($dados);

        return back()->with('sucesso', 'Familiar cadastrado com sucesso.');
    }

    public function destroyFamiliar(Familiar $familiar)
    {
        $familiar->delete();

        return back()->with('sucesso', 'Familiar removido.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome_completo' => ['required', 'string', 'max:255'],
            'nome_social' => ['nullable', 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date'],
            'sexo' => ['nullable', 'string', 'max:50'],
            'identidade_genero' => ['nullable', 'string', 'max:100'],
            'cor_raca' => ['nullable', 'string', 'max:50'],
            'naturalidade' => ['nullable', 'string', 'max:255'],
            'nacionalidade' => ['nullable', 'string', 'max:255'],
            'rg' => ['nullable', 'string', 'max:50'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'certidao_nascimento' => ['nullable', 'string', 'max:100'],
            'rn' => ['nullable', 'string', 'max:50'],
            'cartao_sus' => ['nullable', 'string', 'max:50'],
            'nis' => ['nullable', 'string', 'max:50'],
            'titulo_eleitor' => ['nullable', 'string', 'max:50'],
            'nome_mae' => ['nullable', 'string', 'max:255'],
            'nome_pai' => ['nullable', 'string', 'max:255'],
            'responsavel_legal' => ['nullable', 'string', 'max:255'],
            'contato_responsavel' => ['nullable', 'string', 'max:100'],
            'endereco_familia' => ['nullable', 'string', 'max:255'],
            'processo_numero' => ['nullable', 'string', 'max:100'],
            'vara' => ['nullable', 'string', 'max:255'],
            'comarca' => ['nullable', 'string', 'max:255'],
            'data_acolhimento' => ['nullable', 'date'],
            'motivo_acolhimento' => ['nullable', 'string'],
            'foto' => ['nullable', 'image', 'max:4096'],
            'status' => ['nullable', 'in:acolhida,desligada'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }
}
