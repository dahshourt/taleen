<?php

namespace App\Http\Requests\RequesterDepartment;

use Illuminate\Foundation\Http\FormRequest;

class RequesterDepartmentRequest extends FormRequest
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
        $id = $this->route('requester_department');
        
        return [
            'name' => 'required|string|max:255|unique:requester_departments,name,' . $id,
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'The department name is required',
            'name.unique' => 'The department name has already been taken',
        ];
    }
}
