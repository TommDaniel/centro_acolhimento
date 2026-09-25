<?php

namespace App\Http\Requests\Auth;

use App\Services\AuditRecorder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmMfaEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer'],
            'code' => ['required', 'digits:6'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->user() !== null) {
            app(AuditRecorder::class)->record(
                'auth.mfa_enrollment_confirmed',
                'denied',
                $this->user(),
                $this->user(),
            );
        }

        parent::failedValidation($validator);
    }
}
