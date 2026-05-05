<?php

namespace App\Http\Requests\Director;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DirectorRequest extends FormRequest
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
            'user_name' => ['required', 'string', 'max:255', 'unique:directors,user_name'],
            'email' => ['required', 'email', 'max:255', 'unique:directors,email'],
            'status' => ['required', 'in:0,1'],
        ];
    }

    public function updateRules(): array
    {
        $directorId = $this->route('director');

        return [
            'user_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('directors', 'user_name')->ignore($directorId),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('directors', 'email')->ignore($directorId),
            ],
            'status' => ['required', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_name.required' => 'The user name field is required.',
            'user_name.unique' => 'This user name has already been taken.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address has already been taken.',
            'status.in' => 'The status field must be either 0 or 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_name' => 'user name',
            'email' => 'email address',
            'status' => 'status',
        ];
    }
}
