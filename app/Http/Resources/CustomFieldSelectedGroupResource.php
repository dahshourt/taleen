<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldSelectedGroupResource extends JsonResource
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
        if ($this->CustomField->type == 'select' && $this->CustomField->related_table) {
            $values = $this->CustomField->getCustomFieldValue();
        }

        return [
            'id' => $this->CustomField->id,
            'type' => $this->CustomField->type,
            'name' => $this->CustomField->name,
            'label' => $this->CustomField->label,
            'class' => $this->CustomField->class,
            'default_value' => $this->CustomField->default_value,
            'related_table' => $this->CustomField->related_table,
            'validation_type_id' => $this->validation_type_id,
            'enable' => $this->enable,
            'values' => $values,

        ];
    }
}
