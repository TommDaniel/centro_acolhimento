<?php

namespace App\Http\Requests;

use App\Http\Controllers\Concerns\EmiteOficio;
use App\Models\Pia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    public function formRedirectUrl(): string
    {
        $pia = $this->route('pia');

        if ($pia instanceof Pia) {
            return route('pias.edit', $pia);
        }

        $childId = $this->integer('crianca_id');

        return route('pias.create', $childId > 0 ? ['crianca_id' => $childId] : []);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->hasFile('anexos') || $this->exists('anexos') || $this->exists('anexos_descricao')) {
            abort(423, 'O envio de anexos do PIA está temporariamente desativado.');
        }

        $pia = $this->route('pia');
        $childRules = ['required', 'exists:criancas,id'];

        if ($pia instanceof Pia) {
            $childRules[] = Rule::in([$pia->crianca_id]);
        }

        $expectedEpisodeRules = $pia instanceof Pia
            ? ['prohibited']
            : ['present', 'nullable', 'integer', 'min:1'];

        return [
            'crianca_id' => $childRules,
            'acolhimento_id' => ['prohibited'],
            'expected_acolhimento_id' => $expectedEpisodeRules,
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
            'crianca_id.in' => 'A pessoa vinculada ao PIA não pode ser alterada.',
            'acolhimento_id.prohibited' => 'O vínculo com o episódio é definido pelo servidor.',
            'expected_acolhimento_id.present' => 'Atualize o formulário antes de registrar o PIA.',
            'expected_acolhimento_id.prohibited' => 'A expectativa de episódio só é aceita na criação do PIA.',
            'anexos.prohibited' => 'O envio de anexos do PIA está temporariamente desativado.',
            'anexos_descricao.prohibited' => 'O envio de anexos do PIA está temporariamente desativado.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->formRedirectUrl();
    }
}
