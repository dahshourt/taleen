<?php

namespace App\Traits\ChangeRequest;

trait ChangeRequestConstants
{
    /**
     * Get required fields for change request creation/update
     */
    protected function getRequiredFields(): array
    {
        return [
            'title', 'description', 'active', 'developer_id', 'tester_id', 'designer_id',
            'requester_id', 'design_duration', 'start_design_time', 'end_design_time',
            'develop_duration', 'start_develop_time', 'end_develop_time', 'test_duration',
            'start_test_time', 'end_test_time', 'depend_cr_id', 'requester_name',
            'requester_email', 'requester_unit', 'requester_division_manager',
            'requester_department', 'application_name', 'testable', 'top_management', 'created_at',
            'updated_at', 'category_id', 'priority_id', 'unit_id', 'department_id',
            'application_id', 'workflow_type_id', 'division_manager', 'creator_mobile_number',
            'calendar', 'CR_duration', 'chnage_requester_id', 'start_CR_time',
            'end_CR_time', 'release_name', 'cr_no','hold'
        ];
    }

    /**
     * Get fields that should be excluded from processing
     */
    protected function getExcludedFields(): array
    {
        return [
            'old_status_id', 'new_status_id', '_method', 'current_status', 'duration',
            'categories', 'cat_name', 'pr_name', 'Applications', 'app_name',
            'depend_cr_name', 'depend_crs', 'test', 'priorities', 'cr_id',
            'assign_to', 'dev_estimation', 'design_estimation', 'testing_estimation',
            'assignment_user_id', '_token', 'attach', 'business_attachments',
            'technical_attachments', 'cap_users', 'analysis_feedback', 'technical_feedback',
            'need_ux_ui', 'business_feedback', 'rejection_reason_id', 'technical_teams',
            'CR_estimation', 'cr_member', 'cr_no', 'deployment_impact', 'need_down_time',
            'proposed_available_time',
        ];
    }

    /**
     * Get workflow type constants
     */
    protected function getWorkflowTypes(): array
    {
        return config('change_request.workflow_types', [
            'normal' => 1,
            'emergency' => 2,
            'maintenance' => 3,
            'enhancement' => 4,
            'release' => 5,
        ]);
    }

    /**
     * Get status IDs from configuration
     */
    protected function getStatusIds(): array
    {
        return \App\Services\StatusConfigService::loadStatusIds();
    }

    /**
     * Get working hours configuration
     */
    protected function getWorkingHours(): array
    {
        return config('change_request.working_hours', [
            'start' => 8,
            'end' => 16,
            'weekend_days' => [5, 6],
            'hours_per_day' => 8,
        ]);
    }

    /**
     * Get default values configuration
     */
    protected function getDefaultValues(): array
    {
        return config('change_request.default_values', []);
    }

    /**
     * Get mail notification settings
     */
    protected function getMailNotificationSettings(): array
    {
        return config('change_request.mail_notifications', [
            'creation' => true,
            'status_change' => true,
            'assignment' => true,
        ]);
    }

    /**
     * Get SLA configuration for a specific workflow type
     */
    protected function getSlaConfiguration(string $workflowType = 'normal'): array
    {
        return config("change_request.sla.{$workflowType}", [
            'response_time' => 24,
            'resolution_time' => 168,
        ]);
    }

    /**
     * Get validation rules for change request fields
     */
    protected function getValidationRules(): array
    {
        return config('change_request.validation', []);
    }

    /**
     * Get custom field configuration
     */
    protected function getCustomFieldConfiguration(): array
    {
        return config('change_request.custom_fields', [
            'enabled' => true,
            'max_per_request' => 20,
        ]);
    }

    /**
     * Get upload configuration
     */
    protected function getUploadConfiguration(): array
    {
        return config('change_request.uploads', [
            'max_file_size' => 10240,
            'allowed_extensions' => ['pdf', 'doc', 'docx'],
        ]);
    }

    /**
     * Check if a workflow type is valid
     */
    protected function isValidWorkflowType(int $workflowTypeId): bool
    {
        return in_array($workflowTypeId, array_values($this->getWorkflowTypes()));
    }

    /**
     * Check if a status ID is a special status
     */
    protected function isSpecialStatus(int $statusId): bool
    {
        $specialStatuses = [
            'pending_production_deployment',
            'production_deployment',
            'business_approval',
            'cr_manager_review',
        ];

        foreach ($specialStatuses as $status) {
            if ($this->getStatusIds()[$status] === $statusId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first CR number for a workflow type
     */
    protected function getFirstCrNumber(int $workflowTypeId): int
    {
        $firstCrNumbers = $this->getDefaultValues()['first_cr_no'];

        return $firstCrNumbers[$workflowTypeId] ?? $firstCrNumbers['default'];
    }

    /**
     * Check if it's a working day
     */
    protected function isWorkingDay(\Carbon\Carbon $date): bool
    {
        $weekendDays = $this->getWorkingHours()['weekend_days'];

        return ! in_array($date->dayOfWeek, $weekendDays);
    }

    /**
     * Check if time is within working hours
     */
    protected function isWorkingTime(\Carbon\Carbon $time): bool
    {
        $workingHours = $this->getWorkingHours();
        $hour = $time->hour;

        return $hour >= $workingHours['start'] && $hour < $workingHours['end'];
    }

    /**
     * Get field mapping for logging
     */
    protected function getFieldMappingForLogging(): array
    {
        return [
            'title' => 'Subject',
            'description' => 'Description',
            'priority_id' => 'Priority',
            'category_id' => 'Category',
            'application_id' => 'Application',
            'analysis_feedback' => 'Analysis Feedback',
            'technical_feedback' => 'Technical Feedback',
            'unit_id' => 'Unit',
            'creator_mobile_number' => 'Creator Mobile',
            'division_manager_id' => 'Division Manager',
            'need_ux_ui' => 'Need UI UX',
            'postpone' => 'Postpone Status',
        ];
    }

    /**
     * Get estimation action types
     */
    protected function getEstimationActionTypes(): array
    {
        return [
            'design' => 'design',
            'development' => 'dev',
            'testing' => 'test',
            'cr' => 'CR',
        ];
    }

    /**
     * Get role column mappings
     */
    protected function getRoleColumnMappings(): array
    {
        return [
            'designer_id' => [
                'start_column' => 'start_design_time',
                'end_column' => 'end_design_time',
                'duration_column' => 'design_duration',
                'action' => 'design',
            ],
            'developer_id' => [
                'start_column' => 'start_develop_time',
                'end_column' => 'end_develop_time',
                'duration_column' => 'develop_duration',
                'action' => 'dev',
            ],
            'tester_id' => [
                'start_column' => 'start_test_time',
                'end_column' => 'end_test_time',
                'duration_column' => 'test_duration',
                'action' => 'test',
            ],
            'chnage_requester_id' => [
                'start_column' => 'start_CR_time',
                'end_column' => 'end_CR_time',
                'duration_column' => 'CR_duration',
                'action' => 'CR',
            ],
        ];
    }
}

