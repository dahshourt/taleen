<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomFieldStatusRequest extends FormRequest
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
        return [
            'statuses' => 'nullable|array',
            'statuses.*.status_id' => 'required_with:statuses|exists:statuses,id',
            'statuses.*.log_message' => 'required_with:statuses|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'statuses.array' => 'Statuses must be an array.',
            'statuses.*.status_id.required_with' => 'Status is required.',
            'statuses.*.status_id.exists' => 'The selected status does not exist.',
            'statuses.*.log_message.required_with' => 'Log message is required.',
            'statuses.*.log_message.string' => 'Log message must be a valid text value.',
        ];
    }
}
