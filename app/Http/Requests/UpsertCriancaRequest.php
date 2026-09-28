<?php

namespace App\Http\Requests;

use App\Models\Crianca;
use Illuminate\Foundation\Http\FormRequest;

class UpsertCriancaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crianca = $this->route('crianca');

        return $crianca instanceof Crianca
            ? $this->user()?->can('update', $crianca) === true
            : $this->user()?->can('create', Crianca::class) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
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
            'foto' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png',
                'max:4096',
                'dimensions:min_width=32,min_height=32,max_width=4096,max_height=4096',
            ],
            'status' => ['nullable', 'in:acolhida,desligada'],
            'observacoes' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'foto.mimetypes' => 'O retrato deve ser uma imagem JPEG ou PNG válida.',
            'foto.max' => 'O retrato deve ter no máximo 4 MB.',
            'foto.dimensions' => 'O retrato deve ter entre 32 e 4096 pixels em cada dimensão.',
        ];
    }
}
