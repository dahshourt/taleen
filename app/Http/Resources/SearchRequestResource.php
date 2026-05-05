<?php

namespace App\Http\Resources;

use auth;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchRequestResource extends JsonResource
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
            'cr_no' => $this->cr_no,
            'title' => $this->title,
            'description' => $this->description,
            'active' => $this->active,
            'testable' => $this->testable,
            'application' => $this->application,
            'priority' => $this->priority,
            'requester' => new RequesterResource($this->requester),
            'developer' => $this->developer,
            'tester' => $this->tester,
            'designer' => $this->designer,
            'user' => auth::user(),
            'current_status' => new ChangeRequestStatusResource($this->getCurrentStatus()),
            'created_at' => $this->created_at->format('d/m/y'),
            'updated_at' => $this->created_at->format('d/m/y'),
        ];
    }
}
