<?php

namespace App\Http\Requests\Statuses;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

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
            'sla' => ['required', 'integer'],
            'log_message' => ['nullable', 'string'],
            'active' => ['integer'],
            'view_technical_team_flag' => ['nullable', 'boolean', 'integer'],
            'view_sr_technical_team_flag' => ['nullable', 'boolean', 'integer'],
            'set_group_id' => ['sometimes', 'array'],
            'set_group_id.*' => ['integer', 'exists:groups,id'],
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
            'sla' => ['required', 'integer'],
            'log_message' => ['nullable', 'string'],
            'active' => ['required', 'integer'],
            'view_technical_team_flag' => ['nullable', 'boolean', 'integer'],
            'view_sr_technical_team_flag' => ['nullable', 'boolean', 'integer'],
            'set_group_id' => ['sometimes', 'array'],
            'set_group_id.*' => ['integer', 'exists:groups,id'],
            'view_group_id' => ['sometimes', 'array'],
            'view_group_id.*' => ['integer', 'exists:groups,id'],
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
