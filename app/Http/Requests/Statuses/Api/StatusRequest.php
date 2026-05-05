<?php

namespace App\Http\Requests\Statuses\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StatusRequest extends FormRequest
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
            'status_name' => ['required', 'string', 'unique:statuses'],
            'stage_id' => ['required', 'integer'],
            'log_message' => ['nullable', 'string'],
            'active' => ['required', 'integer'],
            'group_id' => ['required', 'array'],
            'group_id.*' => ['integer', 'exists:groups,id'],
            'view_group_id' => ['sometimes', 'array'],
            'view_group_id.*' => ['integer', 'exists:groups,id'],

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

            'status_name' => ['required', 'string', 'unique:statuses,status_name,' . request()->status],
            'stage_id' => ['required', 'integer'],
            'log_message' => ['nullable', 'string'],
            'active' => ['required', 'integer'],
            'group_id' => ['sometimes', 'array'],
            'group_id.*' => ['integer', 'exists:groups,id'],
            'view_group_id' => ['sometimes', 'array'],
            'view_group_id.*' => ['integer', 'exists:groups,id'],
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
