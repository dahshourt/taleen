<?php

namespace App\Http\Requests\Change_Request\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class attachments_CRS_Request extends FormRequest
{
    /**
     * Determine if the supervisor is authorized to make this request.
     *
     * @return bool
     */
    // public function authorize()
    // {
    //    return true;
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
            'filesdata.*' => 'mimes:jpg,jpeg,png,bmp,pdf,docx,xlsx,zip,rar|max:30000',

        ];
    }

    public function updateRules()
    {
        return [

            'filesdata.*' => 'mimes:jpg,jpeg,png,bmp|max:20000',

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
