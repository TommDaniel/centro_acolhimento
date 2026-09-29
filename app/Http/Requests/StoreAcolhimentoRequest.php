<?php

namespace App\Http\Requests;

use App\Models\Acolhimento;
use App\Models\Crianca;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcolhimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crianca = $this->route('crianca');

        return $crianca instanceof Crianca
            && $this->user()?->can('view', $crianca) === true
            && $this->user()?->can('create', Acolhimento::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ingresso_em' => ['required', 'date_format:Y-m-d\TH:i'],
            'motivo' => ['required', 'string', 'max:4000'],
            'fundamento' => ['nullable', 'string', 'max:4000'],
            'origem_codigo' => ['required', Rule::in(array_keys(Acolhimento::ORIGENS))],
            'origem_complemento' => [
                Rule::requiredIf(fn (): bool => $this->input('origem_codigo') === 'outro'),
                'nullable',
                'string',
                'max:255',
                'prohibited_unless:origem_codigo,outro',
            ],
            'orgao_condutor_codigo' => ['required', Rule::in(array_keys(Acolhimento::ORGAOS_CONDUTORES))],
            'orgao_condutor_complemento' => [
                Rule::requiredIf(fn (): bool => $this->input('orgao_condutor_codigo') === 'outro'),
                'nullable',
                'string',
                'max:255',
                'prohibited_unless:orgao_condutor_codigo,outro',
            ],
            'pessoa_condutora' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
            'crianca_id' => ['prohibited'],
            'unidade_id' => ['prohibited'],
            'organizacao_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'recorded_at' => ['prohibited'],
            'situacao_resultante' => ['prohibited'],
            'encerrado_em' => ['prohibited'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedForPersistence(): array
    {
        $data = $this->validated();
        $data['ingresso_em'] = CarbonImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $data['ingresso_em'],
            'America/Sao_Paulo',
        )->utc();

        foreach (['motivo', 'fundamento', 'origem_complemento', 'orgao_condutor_complemento', 'pessoa_condutora'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
            }
        }

        return $data;
    }
}
