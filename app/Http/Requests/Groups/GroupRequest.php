<?php

namespace App\Http\Requests\Groups;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GroupRequest extends FormRequest
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
    // public function rules()
    // {
    //     return [
    //         'title' => ['required','string'],
    //         'description' => ['required','string'],
    //         'parent_id' => ['sometimes','integer', 'exists:groups,id'],
    //         'head_group_name' => ['sometimes','string'],
    //         'head_group_email' => ['sometimes','email'],
    //         'active' => ['required'],
    //     ];

    // }

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
            'title' => ['required', 'string', 'unique:groups'],
            'description' => ['required', 'string'],
            'man_power' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer', 'exists:groups,id'],
            'head_group_name' => ['nullable', 'string'],
            'head_group_email' => ['nullable', 'email'],
            // 'application_id' => ['required'],
        ];
    }

    /**
     * Get the update validation rules that apply to the request.
     *
     * @return array
     */
    public function updateRules()
    {
        $groupId = $this->route('group'); // assuming the route parameter is named 'id'

        // dd($groupId);
        return [
            'title' => [
                'required',
                'string',

                'unique:groups,title,' . $groupId,
            ],
            'description' => ['required', 'string'],
            'man_power' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer', 'exists:groups,id'],
            'head_group_name' => ['nullable', 'string'],
            'head_group_email' => ['nullable', 'email'],
            // 'application_id' => ['required'],

        ];
    }

    protected function prepareForValidation()
    {
        // Set 'active' to 1 if not present in the request
        $this->merge([
            'active' => $this->has('active') ? $this->input('active') : '0',
        ]);
    }

    /*protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json([
            'message' => $validator->messages()
          ], 422));
    }*/
    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    // public function attributes()
    // {

    // }
}
