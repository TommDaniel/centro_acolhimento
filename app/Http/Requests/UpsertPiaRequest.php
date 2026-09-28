<?php

namespace App\Http\Requests;

use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\Pia;
use Illuminate\Foundation\Http\FormRequest;

class UpsertPiaRequest extends FormRequest
{
    use EmiteOficio;

    public function authorize(): bool
    {
        $pia = $this->route('pia');

        return $pia instanceof Pia
            ? $this->user()?->can('update', $pia) === true
            : $this->user()?->can('create', Pia::class) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        if ($this->hasFile('anexos') || $this->exists('anexos') || $this->exists('anexos_descricao')) {
            abort(423, 'O envio de anexos do PIA está temporariamente desativado.');
        }

        return [
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
            'anexos' => ['prohibited'],
            'anexos_descricao' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'anexos.prohibited' => 'O envio de anexos do PIA está temporariamente desativado.',
            'anexos_descricao.prohibited' => 'O envio de anexos do PIA está temporariamente desativado.',
        ];
    }
}
