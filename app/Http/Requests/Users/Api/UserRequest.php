<?php

namespace App\Http\Requests\Users\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'user_type' => ['required'],
            // 'email' => ['required_if:user_type,0','email', 'unique:users,email'],
            'email' => ['sometimes', 'nullable', 'required_if:user_type,0', 'email', 'unique:users,email'],
            'password' => ['sometimes', 'nullable', 'required_if:user_type,0', 'confirmed'],
            'default_group' => ['required', 'integer', 'exists:groups,id'],
            'active' => ['required', 'integer'],
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
            'email' => ['sometimes', 'nullable', 'required_if:user_type,0', 'email', 'unique:users,email,' . request()->user],
            'user_name' => ['required', 'string', 'required', 'unique:users,user_name,' . request()->user],
            'user_type' => ['required'],
            'password' => ['sometimes', 'nullable', 'required_if:user_type,0', 'confirmed'],
            'default_group' => ['required', 'integer', 'required', 'exists:groups,id'],
            'active' => ['required', 'integer'],
            'group_id' => ['required', 'array', 'required'],
            'group_id.*' => ['integer', 'required', 'exists:groups,id'],
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
