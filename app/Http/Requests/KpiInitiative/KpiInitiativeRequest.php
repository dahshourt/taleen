<?php

namespace App\Http\Requests\KpiInitiative;

use Illuminate\Foundation\Http\FormRequest;

class KpiInitiativeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'pillar_id' => ['required', 'exists:kpi_pillars,id'],
            'status' => ['sometimes', 'in:1,0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'pillar_id.required' => 'The pillar field is required.',
            'pillar_id.exists' => 'The selected pillar is invalid.',
            'status.in' => 'The status must be either active or inactive.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'pillar_id' => 'pillar',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->has('status') ? '1' : '0',
        ]);
    }
}

