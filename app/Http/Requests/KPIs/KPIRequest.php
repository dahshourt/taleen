<?php

namespace App\Http\Requests\KPIs;

use Illuminate\Foundation\Http\FormRequest;

class KPIRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return $this->createRules();
        }

        return $this->updateRules();
    }

    public function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:kpis,name'],
            'priority' => ['required', 'in:Critical,High,Medium,Low'],
            'pillar_id' => ['required', 'exists:kpi_pillars,id'],
            'initiative_id' => ['required', 'exists:kpi_initiatives,id'],
            'sub_initiative_id' => ['nullable', 'exists:kpi_sub_initiatives,id'],
            'bu' => ['required', 'string', 'max:255'],
            'sub_bu' => ['nullable', 'string', 'max:255'],
            // Timeline fields are shown for both classifications,
            // but only required when classification is CR
            'target_launch_quarter' => ['required_if:classification,CR', 'nullable', 'in:Q1,Q2,Q3,Q4'],
            'target_launch_year' => ['required_if:classification,CR', 'nullable', 'integer', 'min:2000', 'max:2100'],
            // Target CRs are used only for CR classification (optional/required_if)
            'target_cr_count' => ['nullable', 'integer', 'min:0', 'required_if:classification,CR'],
            // Projects are used only for PM classification
            'project_ids' => ['required_if:classification,PM', 'nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'type_id' => ['required', 'exists:kpi_types,id'],
            'kpi_brief' => ['required', 'string'],
            'classification' => ['required', 'in:CR,PM'],
            // Requester email is optional; validate only if present
            'requester_email' => ['nullable', 'email', 'max:255'],
            // 'status' => ['required', 'in:Open,In Progress,Delivered'],
            'created_by' => ['nullable', 'exists:users,id'],
        ];
    }

    public function updateRules(): array
    {
        $kpiId = $this->route('kpi');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:kpis,name,' . $kpiId,
            ],
            'priority' => ['required', 'in:Critical,High,Medium,Low'],
            'pillar_id' => ['required', 'exists:kpi_pillars,id'],
            'initiative_id' => ['required', 'exists:kpi_initiatives,id'],
            'sub_initiative_id' => ['nullable', 'exists:kpi_sub_initiatives,id'],
            'bu' => ['required', 'string', 'max:255'],
            'sub_bu' => ['nullable', 'string', 'max:255'],
            // Timeline fields are shown for both classifications,
            // but only required when classification is CR
            'target_launch_quarter' => ['required_if:classification,CR', 'nullable', 'in:Q1,Q2,Q3,Q4'],
            'target_launch_year' => ['required_if:classification,CR', 'nullable', 'integer', 'min:2000', 'max:2100'],
            // Target CRs are used only for CR classification (optional/required_if)
            'target_cr_count' => ['nullable', 'integer', 'min:0', 'required_if:classification,CR'],
            'type_id' => ['required', 'exists:kpi_types,id'],
            'kpi_brief' => ['required', 'string'],
            'classification' => ['required', 'in:CR,PM'],
            // 'status' => ['required', 'in:Open,In Progress,Delivered'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'created_by' => ['nullable', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The KPI name field is required.',
            'name.unique' => 'This KPI name has already been taken.',
            'priority.required' => 'The priority field is required.',
            'priority.in' => 'The priority must be one of: Critical, High, Medium, Low.',
            'strategic_pillar.required' => 'The strategic pillar field is required.',
            'initiative.required' => 'The initiative field is required.',
            'bu.required' => 'The business unit field is required.',
            'target_launch_quarter.required' => 'The target launch quarter field is required.',
            'target_launch_quarter.in' => 'The target launch quarter must be one of: Q1, Q2, Q3, Q4.',
            'target_launch_year.required' => 'The target launch year field is required.',
            'target_launch_year.integer' => 'The target launch year must be a valid year.',
            'target_launch_year.min' => 'The target launch year must be after 2000.',
            'target_launch_year.max' => 'The target launch year cannot exceed 2100.',
            'type.required' => 'The type field is required.',
            'type.in' => 'The type must be one of: Test Type 1, Test Type 2, Test Type 3, Test Type 4.',
            'kpi_brief.required' => 'The KPI brief field is required.',
            'classification.required' => 'The classification field is required.',
            'classification.in' => 'The classification must be either CR or PM.',
            'status.required' => 'The status field is required.',
            'status.in' => 'The status must be one of: Open, In Progress, Delivered.',
            'created_by.exists' => 'The selected user does not exist.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'KPI Name',
            'priority' => 'Priority',
            'strategic_pillar' => 'Strategic Pillar',
            'initiative' => 'Initiative',
            'sub_initiative' => 'Sub-Initiative',
            'bu' => 'Business Unit',
            'sub_bu' => 'Sub-Business Unit',
            'target_launch_quarter' => 'Target Launch Quarter',
            'target_launch_year' => 'Target Launch Year',
            'type' => 'Type',
            'kpi_brief' => 'KPI Brief',
            'classification' => 'Classification',
            'status' => 'Status',
            'created_by' => 'Created By',
        ];
    }
}
