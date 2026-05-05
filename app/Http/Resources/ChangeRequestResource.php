<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChangeRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        return [

            'id' => $this->id,
            'title' => $this->title,
            'workflow_type' => $this->workflow_type_id,
            'description' => $this->description,
            'active' => $this->active,
            'testable' => $this->testable,
            'application_id' => $this->application ? $this->application->id : '',
            'priority' => $this->priority,
            'category_id' => $this->category ? $this->category->id : '',
            'depend_cr' => $this->depend_cr,
            'requester' => new RequesterResource($this->requester),
            'requester_name' => $this->requester ? $this->requester->name : '',
            'requester_email' => $this->requester ? $this->requester->email : '',
            'developer' => $this->developer,
            'tester' => $this->tester,
            'designer' => $this->designer,
            //  'status_id'                        =>     $this->Status->status_name,
            'helpdesk_id' => $this->helpdesk_id,
            'unit_id' => $this->unit_id,
            'priority_id' => $this->priority_id,
            'end_test_time' => $this->end_test_time,
            'start_test_time' => $this->start_test_time,
            'test_duration' => $this->test_duration,
            'end_develop_time' => $this->end_develop_time,
            'start_develop_time' => $this->start_develop_time,
            'develop_duration' => $this->develop_duration,
            'end_design_time' => $this->end_design_time,
            'start_design_time' => $this->start_design_time,
            'design_duration' => $this->design_duration,
            'cr_no' => $this->cr_no,
            'division_manager_id' => $this->division_manager_id,
            'division_manager' => $this->division_manager,
            'current_status' => new ChangeRequestStatusResource($this->current_status),
            'set_status' => WorkFlowResource::collection($this->set_status),
            'assign_to' => $this->assign_to ? ChangeRequestUserResource::collection($this->assign_to) : null,

            'man_days' => $this->man_days,
            'release' => $this->release,
            'associated' => $this->associated,
            'depend_on' => $this->depend_on,
            'analysis_feedback' => $this->analysis_feedback,
            'technical_feedback' => $this->technical_feedback,
            'approval' => $this->approval,
            'need_design' => $this->need_design,
            'impacted_services' => $this->impacted_services,
            'impact_during_deployment' => $this->impact_during_deployment,
            'release_delivery_date' => $this->release_delivery_date,
            'release_name' => $this->release_name,
            'release_receiving_date' => $this->release_receiving_date,
            'need_iot_e2e_testing' => $this->need_iot_e2e_testing,
            'te_testing_date' => $this->te_testing_date,
            'uat_date' => $this->uat_date,
            'cost' => $this->cost,
            'uat_duration' => $this->uat_duration,
            'parent_id' => $this->parent_id,
            'rejection_reason_id' => $this->rejection_reason_id,
            'creator_mobile_number' => $this->creator_mobile_number,
            'vendor_id' => $this->vendor_id,
            'requester_department' => $this->requester && isset($this->requester->department) ? $this->requester->department->name : '',
            'attachments' => AttachmentsResource::collection($this->attachments),
            'old_status_id' => $this->Req_status()->latest('id')->first()?->old_status_id ?? '',
        ];
    }
}
