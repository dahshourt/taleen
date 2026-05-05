<?php

namespace App\Http\Requests\Users;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'user_name' => ['required', 'string', 'unique:users,user_name'],
            'email' => ['required', 'email', 'unique:users,email'],
            'default_group' => ['required', 'integer', 'exists:groups,id'],
            'group_id' => ['required', 'array'],
            'group_id.*' => ['integer', 'exists:groups,id'],
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
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email,' . request('user_id')],
            'user_name' => ['required', 'string', 'unique:users,user_name,' . request()->user],
            'default_group' => ['required', 'integer', 'exists:groups,id'],
            'group_id' => ['required', 'array'],
            'group_id.*' => ['integer', 'exists:groups,id'],
        ];
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
