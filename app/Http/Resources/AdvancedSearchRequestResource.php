<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdvancedSearchRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $currentStatus = $this->getCurrentStatus();

        return [
            'id' => $this->id,
            'cr_no' => $this->cr_no,
            'title' => $this->title,
            'description' => $this->description,
            'active' => $this->active,
            'testable' => $this->testable,
            'develop_duration' => $this->develop_duration,
            'design_duration' => $this->design_duration,
            'test_duration' => $this->test_duration,
            'application' => $this->application ? $this->application->name : '',
            'requester_name' => $this->requester ? $this->requester->name : '',
            'requester_email' => $this->requester ? $this->requester->email : '',
            'category' => $this->category ? $this->category->name : '',
            'department' => $this->requester && isset($this->requester->department) ? $this->requester->department->name : '',
            'developer' => $this->developer,
            'tester' => $this->tester,
            'workflow_type_id' => $this->workflow_type_id,
            'new_status_id' => $this->Req_status()->latest('id')->first()?->new_status_id ?? '',
            'designer' => $this->designer,
            'created_at' => $this->created_at->format('d-M-Y'),
            'updated_at' => $this->created_at->format('d-M-Y'),
            'current_status' => $this->getCurrentStatus()?->same_time ? $this->getCurrentStatus()->to_status_label : $this->getCurrentStatus()?->status?->status_name,
            'on_behalf' => $this->changeRequestCustomFields->where('custom_field_name', 'on_behalf')->where('custom_field_value', '1')->first() ? 'Yes' : 'No',
            'cr_type_name' => \App\Models\CrType::find($this->changeRequestCustomFields->where('custom_field_name', 'cr_type')->first()?->custom_field_value)?->name ?? '',
        ];
    }
}
