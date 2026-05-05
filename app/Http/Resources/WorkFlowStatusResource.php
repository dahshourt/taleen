<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkFlowStatusResource extends JsonResource
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
            'to_status_id' => $this->to_status_id,
            'default_to_status' => $this->default_to_status,
            'status' => new RequestStatusDetails($this->to_status),
        ];
    }
}
