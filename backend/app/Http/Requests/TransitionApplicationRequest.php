<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_status' => ['required', 'string', Rule::enum(ApplicationStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'target_status.enum' => 'El estado destino no existe. Valores válidos: '.implode(', ', ApplicationStatus::values()).'.',
        ];
    }

    public function targetStatus(): ApplicationStatus
    {
        return ApplicationStatus::from($this->validated('target_status'));
    }
}
