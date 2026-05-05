<?php

namespace App\Http\Requests\Prerequisites;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PrerequisitesRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'promo_id' => ['required', 'exists:change_request,id'],
            'group_id' => ['required', 'exists:groups,id'],
            'requester_department' => ['required', 'string', 'max:255'],
            'requester_mobile' => ['required', 'string', 'size:11', 'regex:/^[0-9]+$/'],
            'status_id' => ['required', 'exists:statuses,id'],
            'comments' => ['nullable', 'string'],
            'attachments' => ['nullable', 'file', 'max:10240'], // Max 10MB
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
            'subject' => ['required', 'string', 'max:255'],
            'promo_id' => ['required', 'exists:change_request,id'],
            'group_id' => ['required', 'exists:groups,id'],
            'requester_department' => ['required', 'string', 'max:255'],
            'requester_mobile' => ['required', 'string', 'size:11', 'regex:/^[0-9]+$/'],
            'status_id' => ['required', 'exists:statuses,id'],
            'comments' => ['nullable', 'string'],
            'attachments' => ['nullable', 'file', 'max:10240'], // Max 10MB
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
            'promo_id.required' => 'Please select a promo',
            'group_id.required' => 'Please select a group',
            'attachments.max' => 'The attachment must not be greater than 10MB',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // If it's a create request, we'll handle status_id in the controller
        if ($this->isMethod('POST')) {
            $this->mergeIfMissing([
                'status_id' => $this->getDefaultStatusId(),
            ]);
        }
    }

    /**
     * Get the default status ID (Open status)
     *
     * @return int|null
     */
    protected function getDefaultStatusId()
    {
        return \App\Models\Status::where('status_name', 'Open')->value('id');
    }

    /**
     * Handle a failed validation attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            redirect()->back()
                ->withErrors($validator)
                ->withInput()
        );
    }
}
