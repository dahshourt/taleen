<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class WorkflowRequest extends FormRequest
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
        $rules = [
            'from_status_id' => ['required', 'int'],
            // 'from_status_name' => ['required','string'],
            // 'to_status_id' => ['required','array'],
            // 'to_status_name' => ['required','array'],
            // 'default_to_status'=>['sometimes','int'],
            'active' => ['required', 'int'],
            // 'to_status_label' => ['sometimes','nullable','string'],
            'default_to_status' => ['sometimes', 'nullable', 'int'],

        ];
        // $rules['to_status_id'] = is_array(request()->to_status_id) ? ['required','array'] : ['required','int'];

        return $rules;
    }

    /**
     * Get the update validation rules that apply to the request.
     *
     * @return array
     */
    public function updateRules()
    {
        return [
            'from_status_id' => ['required', 'int'],
            // 'from_status_name' => ['required','string'],
            // 'to_status_id' => ['required','int'],
            // 'to_status_name' => ['required','string'],
            'default_to_status' => ['sometimes', 'nullable', 'int'],

            'to_status_label' => ['sometimes', 'nullable', 'string'],
        ];
    }

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
