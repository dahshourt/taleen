<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
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
        $rules = $this->isMethod('post') ? $this->createRules() : $this->updateRules();

        return $rules;
    }

    /**
     * Get the validation rules for creating a project.
     *
     * @return array
     */
    protected function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Not Started,In Progress,Delivered,On-Hold,Canceled'],
            'project_manager_name' => ['required', 'string', 'max:255'],
            'quarters' => ['nullable', 'array'],
            'quarters.*.quarter' => ['required', 'in:Q1,Q2,Q3,Q4'],
            'quarters.*.milestones' => ['nullable', 'array'],
            'quarters.*.milestones.*.milestone' => ['nullable', 'string'],
            'quarters.*.milestones.*.status' => ['nullable', 'in:Not Started,In Progress,Delivered,On-Hold,Canceled'],
        ];
    }

    /**
     * Get the validation rules for updating a project.
     *
     * @return array
     */
    protected function updateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Not Started,In Progress,Delivered,On-Hold,Canceled'],
            'project_manager_name' => ['required', 'string', 'max:255'],
            'quarters' => ['nullable', 'array'],
            'quarters.*.id' => ['nullable', 'integer'],
            'quarters.*.quarter' => ['required', 'in:Q1,Q2,Q3,Q4'],
            'quarters.*.milestones' => ['nullable', 'array'],
            'quarters.*.milestones.*.id' => ['nullable', 'integer'],
            'quarters.*.milestones.*.milestone' => ['nullable', 'string'],
            'quarters.*.milestones.*.status' => ['nullable', 'in:Not Started,In Progress,Delivered,On-Hold,Canceled'],
            'deleted_quarter_ids' => ['nullable', 'array'],
            'deleted_quarter_ids.*' => ['integer'],
        ];
    }
}
