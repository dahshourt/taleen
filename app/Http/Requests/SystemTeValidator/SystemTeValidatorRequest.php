<?php

namespace App\Http\Requests\SystemTeValidator;

use Illuminate\Foundation\Http\FormRequest;

class SystemTeValidatorRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'active' => $this->has('active') ? 1 : 0,
        ]);
    }

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'system_id' => 'nullable|exists:applications,id',
            'active' => 'boolean',
        ];
    }
}
