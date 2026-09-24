<?php

namespace App\Http\Requests;

use App\Models\Evento;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpsertEventoRequest extends FormRequest
{
    private const UNIT_TIMEZONE = 'America/Sao_Paulo';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $evento = $this->route('evento');

        return $evento instanceof Evento
            ? ($this->user()?->can('update', $evento) ?? false)
            : ($this->user()?->can('create', Evento::class) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $instantFormat = 'Y-m-d\TH:i:s';

        return [
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:visita,audiencia,atendimento,tarefa,outro'],
            'descricao' => ['nullable', 'string'],
            'inicio' => [
                'required',
                $this->boolean('dia_inteiro') ? 'date_format:Y-m-d' : 'date_format:'.$instantFormat,
            ],
            'fim' => [
                'nullable',
                'exclude_if:dia_inteiro,true',
                'date_format:'.$instantFormat,
                'after_or_equal:inicio',
            ],
            'dia_inteiro' => ['required', 'boolean'],
            'crianca_id' => ['nullable', 'integer', 'exists:criancas,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedForPersistence(): array
    {
        $data = $this->validated();
        $isAllDay = $this->boolean('dia_inteiro');

        $data['dia_inteiro'] = $isAllDay;
        $data['inicio'] = $this->localDateTimeToUtc(
            $data['inicio'],
            $isAllDay ? '!Y-m-d' : '!Y-m-d\TH:i:s',
        );
        $data['fim'] = $isAllDay || empty($data['fim'])
            ? null
            : $this->localDateTimeToUtc($data['fim'], '!Y-m-d\TH:i:s');

        return $data;
    }

    private function localDateTimeToUtc(string $value, string $format): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat($format, $value, self::UNIT_TIMEZONE)->utc();
    }
}
