<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MyAssignmentsCRSResource extends JsonResource
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
            'cr_no' => $this->cr_no,
            'current_status' => new ChangeRequestStatusResource($current_status),
        ];
    }
}
