<?php

namespace App\Http\Requests\CustomField;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $customFieldId = $this->route('custom_field') ?? $this->route('id');

        return [
            'type' => [
                'required',
                'string',
                Rule::in(array_keys(config('input_types', []))),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z_]+$/',
                Rule::unique('custom_fields', 'name')->ignore($customFieldId),
            ],
            'label' => [
                'required',
                'string',
                'max:255',
            ],
            'class' => [
                'nullable',
                'string',
                'max:255',
            ],
            'default_value' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'related_table' => [
                'nullable',
                'required_if:type,select,multiselect',
                'string',
                'max:255',
            ],
            'active' => [
                'sometimes',
                'in:1,0',
            ],
            'note' => ['nullable', 'string'],
            'log_message' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'type.required' => 'The type field is required.',
            'type.in' => 'The selected type is invalid.',
            'name.required' => 'The name field is required.',
            'name.unique' => 'The name has already been taken.',
            'name.regex' => 'The name may only contain letters and underscores (no numbers allowed).',
            'label.required' => 'The label field is required.',
            'label.max' => 'The label may not be greater than 255 characters.',
            'class.max' => 'The class may not be greater than 255 characters.',
            'default_value.max' => 'The default value may not be greater than 1000 characters.',
            'related_table.max' => 'The related table may not be greater than 255 characters.',
            'log_message.string' => 'The log message must be a valid text value.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'type' => 'input type',
            'name' => 'field name',
            'label' => 'field label',
            'class' => 'CSS class',
            'default_value' => 'default value',
            'related_table' => 'related table',
            'active' => 'status',
            'note' => 'note',
            'log_message' => 'log message',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert name to lowercase and replace spaces with underscores
        if ($this->has('name')) {
            $this->merge([
                'name' => strtolower(str_replace(' ', '_', trim($this->name))),
            ]);
        }

        // Convert active to boolean if present
        $this->merge([
            'active' => $this->has('active') ? '1' : '0',
        ]);
    }
}
