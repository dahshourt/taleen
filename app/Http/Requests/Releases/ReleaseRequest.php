<?php

namespace App\Http\Requests\Releases;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:releases,name'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'priority_id' => ['required', 'exists:priorities,id'],
            'creator_rtm_name' => ['required', 'string', 'max:255'],
            'rtm_email' => ['required', 'email', 'max:255', new \App\Rules\ActiveDirectoryEmail],
            'release_description' => ['required', 'string'],
            'release_start_date' => ['required', 'date'],
            'go_live_planned_date' => ['required', 'date'],
            'technical_feedback' => ['nullable', 'string'],
            'technical_attachment' => ['nullable', 'file', 'max:10240'],
        ];
    }

    /**
     * Get the update validation rules that apply to the request.
     *
     * @return array
     */
    public function updateRules()
    {
        // Determine if the release is in an editable status
        $editableStatuses = ['Planned', 'Development', 'ATP Review', 'Vendor Internal Test', 'IOT', 'E2E'];
        $release = \App\Models\Release::find($this->route('release'));
        $currentStatusName = $release?->releaseStatusObj?->name ?? $release?->status?->name ?? '';
        $isEditable = in_array($currentStatusName, $editableStatuses);

        // Planning Details: required only when the fields are editable (submitted as inputs)
        $planningRule = $isEditable ? 'required' : 'nullable';

        return [
            // RTM Email should be updated too if it's placed in any generic forms, but it's not present here. Adding it just in case it passes through updates.
            'rtm_email' => ['nullable', 'email', 'max:255', new \App\Rules\ActiveDirectoryEmail],

            // Planning Details — required only in editable statuses
            'release_description' => [$planningRule, 'string'],
            'priority_id' => [$planningRule, 'exists:priorities,id'],
            'release_start_date' => [$planningRule, 'date'],
            'go_live_planned_date' => [$planningRule, 'date'],
            'responsible_rtm_id' => [$planningRule, 'exists:users,id'],

            // Testing Schedule Dates
            'atp_review_start_date' => ['nullable', 'date'],
            'atp_review_end_date' => ['nullable', 'date', 'after_or_equal:atp_review_start_date'],
            'vendor_internal_test_start_date' => ['nullable', 'date'],
            'vendor_internal_test_end_date' => ['nullable', 'date', 'after_or_equal:vendor_internal_test_start_date'],
            'iot_start_date' => ['nullable', 'date'],
            'iot_end_date' => ['nullable', 'date', 'after_or_equal:iot_start_date'],
            'e2e_start_date' => ['nullable', 'date'],
            'e2e_end_date' => ['nullable', 'date', 'after_or_equal:e2e_start_date'],
            'uat_start_date' => ['nullable', 'date'],
            'uat_end_date' => ['nullable', 'date', 'after_or_equal:uat_start_date'],
            'smoke_test_start_date' => ['nullable', 'date'],
            'smoke_test_end_date' => ['nullable', 'date', 'after_or_equal:smoke_test_start_date'],

            // Technical
            'technical_feedback' => ['nullable', 'string'],
            'technical_attachment' => ['nullable', 'file', 'max:10240'],

            // Status (only sent when status is changing)
            'status' => ['nullable', 'exists:release_statuses,id'],
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
            'priority_id' => 'Priority',
            'responsible_rtm_id' => 'Responsible RTM',
            'release_start_date' => 'Release Start Date',
            'go_live_planned_date' => 'Go Live Planned Date',
            'atp_review_start_date' => 'ATP Review Start Date',
            'atp_review_end_date' => 'ATP Review End Date',
            'vendor_internal_test_start_date' => 'Vendor Internal Test Start Date',
            'vendor_internal_test_end_date' => 'Vendor Internal Test End Date',
            'iot_start_date' => 'IOT Start Date',
            'iot_end_date' => 'IOT End Date',
            'e2e_start_date' => 'E2E Start Date',
            'e2e_end_date' => 'E2E End Date',
            'uat_start_date' => 'UAT Start Date',
            'uat_end_date' => 'UAT End Date',
            'smoke_test_start_date' => 'Smoke Test Start Date',
            'smoke_test_end_date' => 'Smoke Test End Date',
        ];
    }
}
