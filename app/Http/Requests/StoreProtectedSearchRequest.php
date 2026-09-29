<?php

namespace App\Http\Requests;

use App\Enums\CriancaSituacaoFiltro;
use App\Models\Crianca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProtectedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Crianca::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:255'],
            'situacao' => ['nullable', Rule::enum(CriancaSituacaoFiltro::class)],
        ];
    }
}
