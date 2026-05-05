<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldGroupResource extends JsonResource
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
            'id'                                => $this->id,
            'form_type'                         => $this->form_type,
            'group_id'                          => $this->group_id,
            'wf_type_id'                        => $this->wf_type_id,
            'custom_field_id'                   => $this->custom_field_id,
            'sort'                              => $this->sort,
            'active'                            => $this->active,
            'validation_type_id'                => $this->validation_type_id,
            'enable'                            => $this->enable,
            'status_id'                         => $this->status_id,
        ];
    }
}
