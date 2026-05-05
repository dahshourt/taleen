<?php

namespace App\Http\Requests\SrServices;

use Illuminate\Foundation\Http\FormRequest;

class SrServiceRequest extends FormRequest
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

    /**
     * Get the create validation rules that apply to the request.
     *
     * @return array
     */
    public function createRules()
    {
        return [
            'sr_category_id' => ['required', 'exists:sr_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'technical_teams' => ['nullable', 'array'],
            'technical_teams.*' => ['exists:groups,id'],
        ];
    }

    /**
     * Get the update validation rules that apply to the request.
     *
     * @return array
     */
    public function updateRules()
    {
        $id = $this->route('sr_service');

        return [
            'sr_category_id' => ['required', 'exists:sr_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'technical_teams' => ['nullable', 'array'],
            'technical_teams.*' => ['exists:groups,id'],
        ];
    }
}
