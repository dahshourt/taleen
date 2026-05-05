<?php

namespace App\Http\Requests\Units;

use App\Rules\DivisionManagerExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return $this->createRules();
        }

        return $this->updateRules();
    }

    public function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:units,name'],
            'manager_name' => ['required', 'email', 'max:255', new DivisionManagerExists()],
            'status' => ['required', 'in:0,1'],
        ];
    }

    public function updateRules(): array
    {
        $unitId = $this->route('unit');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'name')->ignore($unitId),
            ],
            'manager_name' => ['required', 'email', 'max:255', new DivisionManagerExists()],
            'status' => ['required', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The unit name field is required.',
            'name.unique' => 'This unit name has already been taken.',
            'manager_name.required' => 'The manager field is required.',
            'manager_name.email' => 'The manager field must be a valid email address.',
            'status.in' => 'The status field must be either 0 or 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Unit name',
            'manager_name' => 'Manager',
            'status' => 'Status',
        ];
    }
}
