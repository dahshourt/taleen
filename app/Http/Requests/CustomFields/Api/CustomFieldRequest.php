<?php

namespace App\Http\Requests\CustomFields\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CustomFieldRequest extends FormRequest
{
    /**
     * Determine if the supervisor is authorized to make this request.
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
    // public function rules()
    // {
    //     return [
    //         'title' => ['required','string'],
    //         'description' => ['required','string'],
    //         'parent_id' => ['sometimes','integer', 'exists:groups,id'],
    //         'head_group_name' => ['sometimes','string'],
    //         'head_group_email' => ['sometimes','email'],
    //         'active' => ['required'],
    //     ];

    // }

    public function rules()
    {
        if ($this->isMethod('POST')) {
            return $this->createRules();
        }

        return $this->updateRules();

    }

    /**
     * Get the create validation rules that apply to the request.
     *
     * @return array
     */
    public function createRules()
    {
        return [
            'type' => ['required', 'string'],
            'label' => ['required', 'string'],
            'name' => ['required', 'string'],
            'class' => ['nullable', 'string'],
            'default_value' => ['nullable', 'string'],
            'related_table' => ['nullable', 'string'],
            'active' => ['required'],
        ];
    }

    /**
     * Get the update validation rules that apply to the request.
     *
     * @return array
     */
    public function updateRules()
    {
        return [
            'type' => ['required', 'string'],
            'label' => ['required', 'string'],
            'name' => ['required', 'string'],
            'class' => ['nullable', 'string'],
            'default_value' => ['nullable', 'string'],
            'related_table' => ['nullable', 'string'],
            'active' => ['required'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->messages(),
        ], 422));
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    // public function attributes()
    // {

    // }
}
