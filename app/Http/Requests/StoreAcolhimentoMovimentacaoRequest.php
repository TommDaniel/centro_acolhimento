<?php

namespace App\Http\Requests;

use App\Enums\AcolhimentoMovimentacaoTipo;
use App\Models\Acolhimento;
use App\Models\Crianca;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcolhimentoMovimentacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crianca = $this->route('crianca');
        $acolhimento = $this->route('acolhimento');

        return $crianca instanceof Crianca
            && $acolhimento instanceof Acolhimento
            && $acolhimento->crianca_id === $crianca->id
            && $this->user()?->can('recordMovement', $acolhimento) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => [
                'required',
                Rule::enum(AcolhimentoMovimentacaoTipo::class)->except(AcolhimentoMovimentacaoTipo::Ingresso),
            ],
            'efetiva_em' => ['required', 'date_format:Y-m-d\TH:i'],
            'motivo' => ['nullable', 'string', 'max:4000', 'required_unless:tipo,retorno'],
            'fundamento' => ['nullable', 'string', 'max:4000'],
            'local_destino' => ['nullable', 'string', 'max:255', 'required_if:tipo,internacao,desacolhimento'],
            'observacao' => ['nullable', 'string', 'max:4000'],
            'idempotency_key' => ['required', 'uuid'],
            'acolhimento_id' => ['prohibited'],
            'crianca_id' => ['prohibited'],
            'unidade_id' => ['prohibited'],
            'organizacao_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'recorded_at' => ['prohibited'],
            'situacao_resultante' => ['prohibited'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedForPersistence(): array
    {
        $data = $this->validated();
        $data['efetiva_em'] = CarbonImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $data['efetiva_em'],
            'America/Sao_Paulo',
        )->utc();

        foreach (['motivo', 'fundamento', 'local_destino', 'observacao'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
            }
        }

        return $data;
    }
}
