<?php

namespace App\Http\Requests\division_manager;

use Illuminate\Foundation\Http\FormRequest;

class division_managerRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:division_managers'],
            'division_manager_email' => ['required', 'string', 'unique:division_managers'],

        ];
    }

    public function updateRules()
    {
        return [
            'name' => ['required', 'string', 'unique:division_managers,name,' . $this->id],
            'division_manager_email' => ['required', 'string', 'unique:division_managers,division_manager_email,' . $this->id],

        ];
    }
}
