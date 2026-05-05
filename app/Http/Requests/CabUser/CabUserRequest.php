<?php

namespace App\Http\Requests\CabUser;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CabUserRequest extends FormRequest
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
            'system_id' => ['required', Rule::unique('system_user_cabs')->where('user_id', request()->user_id)],
            'user_id' => ['required', Rule::unique('system_user_cabs')->where('system_id', request()->system_id)],

        ];
    }

    public function updateRules()
    {
        return [
            'system_id' => ['required', Rule::unique('system_user_cabs')->where('user_id', request()->user_id)->ignore($this->id)],
            'user_id' => ['required', Rule::unique('system_user_cabs')->where('system_id', request()->system_id)->ignore($this->id)],

        ];
    }
}
