<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChangeRequestListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $current_status = $this->ListCurrentStatus();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'active' => $this->active,
            'testable' => $this->testable,
            'application' => $this->application,
            'priority' => $this->priority,
            'category' => $this->category,
            'depend_cr' => $this->depend_cr,
            'requester' => new RequesterResource($this->requester),
            'developer' => $this->developer,
            'tester' => $this->tester,
            'designer' => $this->designer,

            'helpdesk_id' => $this->helpdesk_id,
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
            'current_status' => new ChangeRequestStatusResource($current_status),
        ];
    }
}
