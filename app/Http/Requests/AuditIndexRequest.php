<?php

namespace App\Http\Requests;

use App\Models\AuditEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditEvent::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', Rule::in(['success', 'denied'])],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
