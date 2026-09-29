<?php

namespace App\Http\Requests;

use App\Models\Crianca;
use App\Models\CriancaInformacaoEscolar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCriancaInformacaoEscolarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crianca = $this->route('crianca');

        return $crianca instanceof Crianca
            && $this->user()?->can('update', $crianca) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return self::schoolRules();
    }

    /** @return array<string, array<mixed>> */
    public static function schoolRules(string $prefix = '', bool $nested = false): array
    {
        $required = $nested ? ['required_with:informacao_escolar'] : ['required'];
        $field = fn (string $name): string => $prefix.$name;

        return [
            $field('situacao_codigo') => [...$required, 'string', Rule::in(array_keys(CriancaInformacaoEscolar::SITUACOES))],
            $field('situacao_complemento') => ['nullable', 'string', 'max:255', "required_if:{$field('situacao_codigo')},outra", "prohibited_unless:{$field('situacao_codigo')},outra"],
            $field('escola_nome') => ['nullable', 'string', 'max:255', "prohibited_if:{$field('situacao_codigo')},nao_informada"],
            $field('rede_codigo') => ['nullable', 'string', Rule::in(array_keys(CriancaInformacaoEscolar::REDES)), "prohibited_if:{$field('situacao_codigo')},nao_informada"],
            $field('rede_complemento') => ['nullable', 'string', 'max:255', "required_if:{$field('rede_codigo')},outra", "prohibited_unless:{$field('rede_codigo')},outra"],
            $field('matricula') => ['nullable', 'string', 'max:100', "prohibited_if:{$field('situacao_codigo')},nao_informada"],
            $field('ano_serie') => ['nullable', 'string', 'max:100', "prohibited_if:{$field('situacao_codigo')},nao_informada"],
            $field('turma') => ['nullable', 'string', 'max:100', "prohibited_if:{$field('situacao_codigo')},nao_informada"],
            $field('turno_codigo') => ['nullable', 'string', Rule::in(array_keys(CriancaInformacaoEscolar::TURNOS)), "prohibited_if:{$field('situacao_codigo')},nao_informada"],
            $field('turno_complemento') => ['nullable', 'string', 'max:255', "required_if:{$field('turno_codigo')},outro", "prohibited_unless:{$field('turno_codigo')},outro"],
            $field('vigente_em') => ['nullable', 'date_format:Y-m-d', "prohibited_if:{$field('situacao_codigo')},nao_informada"],
            $field('fonte_codigo') => [...$required, 'string', Rule::in(array_keys(CriancaInformacaoEscolar::FONTES))],
            $field('fonte_complemento') => ['nullable', 'string', 'max:255', "required_if:{$field('fonte_codigo')},outra", "prohibited_unless:{$field('fonte_codigo')},outra"],
            $field('idempotency_key') => [...$required, 'uuid'],
            $field('crianca_id') => ['prohibited'],
            $field('organizacao_id') => ['prohibited'],
            $field('created_by') => ['prohibited'],
            $field('recorded_at') => ['prohibited'],
            $field('versao_anterior_id') => ['prohibited'],
        ];
    }
}
