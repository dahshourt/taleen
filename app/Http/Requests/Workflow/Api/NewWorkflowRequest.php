<?php

namespace App\Http\Requests\Workflow\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NewWorkflowRequest extends FormRequest
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
            'type_id' => ['required'],
            'from_status_id' => ['required'],
            'active' => ['required', 'int'],
            // 'to_status_label' => ['sometimes','nullable','string'],
            'to_status_id' => ['required'],
            'default_to_status' => ['sometimes', 'nullable', 'int'],
            'log_message' => ['sometimes', 'nullable', 'string'],

        ];
        // $rules['to_status_id'] = is_array(request()->to_status_id) ? ['required','array'] : ['required','int'];

    }

    /**
     * Get the update validation rules that apply to the request.
     *
     * @return array
     */
    public function updateRules()
    {
        $rules = [
            'from_status_id' => ['required'],

            // 'to_status_label' => ['sometimes','nullable','string'],
            'default_to_status' => ['sometimes', 'nullable', 'int'],
            'log_message' => ['sometimes', 'nullable', 'string'],

        ];
        $rules['to_status_id'] = is_array(request()->to_status_id) ? ['required', 'array'] : ['required', 'int'];

        return $rules;

    }

    /*protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json([
            'message' => $validator->messages()
          ], 422));
    }*/
    protected function prepareForValidation()
    {
        // Set 'active' to 1 if not present in the request
        $this->merge([
            'active' => $this->has('active') ? $this->input('active') : '0',
        ]);
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
