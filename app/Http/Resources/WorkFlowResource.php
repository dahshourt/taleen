<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkFlowResource extends JsonResource
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
            'active' => $this->active,
            'from_status' => new RequestStatusDetails($this->from_status),
            'same_time' => $this->same_time,
            'workflow_type' => $this->workflow_type,
            'type_id' => $this->type_id,
            'to_status_label' => $this->to_status_label,
            'to_status' => WorkFlowStatusResource::collection($this->workflowstatus->sortByDesc('default_to_status')),
        ];
    }
}
