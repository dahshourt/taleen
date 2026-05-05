<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldSelectedGroupStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $values = null;
        if ($this->type == 'select') {
            $values = $this->getCustomFieldValue();
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->label,
            'class' => $this->class,
            'default_value' => $this->default_value,
            'related_table' => $this->related_table,
            'enable' => $this->enable,
            'values' => $values,

        ];
    }
}
