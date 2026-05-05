<?php

namespace App\Http\Requests\KpiSubInitiative;

use Illuminate\Foundation\Http\FormRequest;

class KpiSubInitiativeRequest extends FormRequest
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
            'initiative_id' => ['required', 'exists:kpi_initiatives,id'],
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
            'initiative_id.required' => 'The initiative field is required.',
            'initiative_id.exists' => 'The selected initiative is invalid.',
            'status.in' => 'The status must be either active or inactive.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'initiative_id' => 'initiative',
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

