<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class UsersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        // dd($this->user_report_to->report_to);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'user_name' => $this->user_name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user_type' => $this->user_type,
            'default_group' => $this->default_group,
            'active' => $this->active,
            'last_login' => $this->last_login,
            'role_id' => $this->role_id,
            'unit_id' => $this->unit_id,
            'department_id' => $this->department_id,
            'user_groups' => $this->user_groups,
            'user_report_to' => $this->user_report_to ? $this->user_report_to->report_to : null,
            'role' => $this->role,

        ];
        // return [$this];
    }
}
