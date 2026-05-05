<?php

namespace App\Http\Requests\HighLevelStatuses;

use Illuminate\Foundation\Http\FormRequest;

class highlevelrequest extends FormRequest
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

        if ($this->isMethod('POST')) {
            return $this->createRules();
        }

        return $this->updateRules();

    }

    public function createRules()
    {
        return [
            'name' => ['required', 'string', 'unique:high_level_statuses'],
            'status_id' => 'required',

        ];
    }

    public function updateRules()
    {
        return [
            'name' => ['required', 'string', 'unique:high_level_statuses,name,' . $this->id],
            'status_id' => 'required',

        ];
    }

    public function messages()
    {
        return [
            'status_id.required' => 'the status is required.',

        ];
    }
}
