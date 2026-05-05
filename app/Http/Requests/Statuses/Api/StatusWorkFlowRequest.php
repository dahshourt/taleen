<?php

namespace App\Http\Requests\Statuses\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StatusWorkFlowRequest extends FormRequest
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
            'type' => ['required', 'integer'],
            'from_stage_id' => ['sometimes', 'integer', 'exists:stages,id'],
            'to_stage_id' => ['sometimes', 'integer', 'exists:stages,id'],
            'from_status_id' => ['required', 'integer', 'exists:statuses,id'],
            'to_status_id' => ['required', 'array'],
            'to_status_id.*' => ['integer', 'integer', 'exists:statuses,id'],

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
            'type' => ['sometimes', 'integer'],
            'from_stage_id' => ['sometimes', 'integer', 'exists:stages,id'],
            'to_stage_id' => ['sometimes', 'integer', 'exists:stages,id'],
            'from_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'to_status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
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
