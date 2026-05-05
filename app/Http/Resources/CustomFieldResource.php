<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldResource extends JsonResource
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
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->label,
            'class' => $this->class,
            'default_value' => $this->default_value,
            'related_table' => $this->related_table,
            'note' => $this->note,
            'active' => $this->active,
            'enable' => $this->enable,
            'custom_field_group' => $this->custom_field_group ?? new CustomFieldGroupResource($this->custom_field_group),
        ];
    }
}
