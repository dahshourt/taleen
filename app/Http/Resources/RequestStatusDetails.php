<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RequestStatusDetails extends JsonResource
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
            'status_name' => $this->status_name,
            'name' => $this->status_name,
            'type' => $this->type,
            'heigh_level' => new HighLevelStatusesResource($this->high_level),
        ];
    }
}
