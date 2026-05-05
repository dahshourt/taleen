<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StatusResource extends JsonResource
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
            'stage_id' => $this->stage_id,
            'active' => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'group_statuses' => GroupStatusResource::collection($this->group_statuses->where('type', 1)),
            'view_group_statuses' => GroupStatusResource::collection($this->group_statuses->where('type', 2)),
            'stage' => new StageResource($this->stage),
            'high_level' => '5',
            'defect' => $this->defect,
        ];
    }
}
