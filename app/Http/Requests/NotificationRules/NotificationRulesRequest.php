<?php

namespace App\Http\Requests\NotificationRules;

use Illuminate\Foundation\Http\FormRequest;

class NotificationRulesRequest extends FormRequest
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
     * Get the create validation rules.
     *
     * @return array
     */
    public function createRules()
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:notification_rules,name'],
            'event_class' => ['required', 'string', 'in:App\Events\ChangeRequestCreated,App\Events\ChangeRequestStatusUpdated,App\Events\MdsStartDateUpdated,App\Events\DefectCreated,App\Events\DefectStatusUpdated,App\Events\PrerequisiteCreated,App\Events\PrerequisiteStatusUpdated,App\Events\ChangeRequestOnHold'],
            'template_id' => ['required', 'exists:notification_templates,id'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            
            // Multiple conditions validation
            'conditions' => ['nullable', 'array'],
            'conditions.*.type' => ['required', 'string', 'in:workflow_type,workflow_type_not,new_status_id,old_status_id,custom_field'],
            'conditions.*.value' => ['nullable', 'string'],
            'conditions.*.custom_field_name' => ['nullable', 'string'],
            'conditions.*.custom_field_value' => ['nullable', 'string'],
            
            // Recipients validation
            'recipients' => ['nullable', 'array'],
            'recipients.*.channel' => ['required', 'in:to,cc,bcc'],
            'recipients.*.recipient_type' => ['required', 'string'],
            'recipients.*.recipient_identifier' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the update validation rules.
     *
     * @return array
     */
    public function updateRules()
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:notification_rules,name,' . $this->route('notification_rule')],
            'event_class' => ['required', 'string', 'in:App\Events\ChangeRequestCreated,App\Events\ChangeRequestStatusUpdated,App\Events\MdsStartDateUpdated,App\Events\DefectCreated,App\Events\DefectStatusUpdated,App\Events\PrerequisiteCreated,App\Events\PrerequisiteStatusUpdated,App\Events\ChangeRequestOnHold'],
            'template_id' => ['required', 'exists:notification_templates,id'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            
            // Multiple conditions validation
            'conditions' => ['nullable', 'array'],
            'conditions.*.type' => ['required', 'string', 'in:workflow_type,workflow_type_not,new_status_id,old_status_id,custom_field'],
            'conditions.*.value' => ['nullable', 'string'],
            'conditions.*.custom_field_name' => ['nullable', 'string'],
            'conditions.*.custom_field_value' => ['nullable', 'string'],
            
            // Recipients validation
            'recipients' => ['nullable', 'array'],
            'recipients.*.channel' => ['required', 'in:to,cc,bcc'],
            'recipients.*.recipient_type' => ['required', 'string'],
            'recipients.*.recipient_identifier' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'Rule Name',
            'event_class' => 'Event Class',
            'template_id' => 'Template',
            'condition_type' => 'Condition Type',
            'condition_value' => 'Condition Value',
            'recipients.*.channel' => 'Recipient Channel',
            'recipients.*.recipient_type' => 'Recipient Type',
            'recipients.*.recipient_identifier' => 'Recipient Identifier',
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'condition_value.required_with' => 'The condition value is required when a condition type is selected.',
            'recipients.*.recipient_identifier.required_if' => 'The recipient identifier is required for this recipient type.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Convert is_active checkbox to boolean
        $this->merge([
            'is_active' => $this->has('is_active') ? true : false,
        ]);
    }
}
