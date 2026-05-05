<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class LogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'log_text' => $this->log_text,
            'user' => $this->user,
            'created_at' => Carbon::parse($this->created_at)->format('Y-m-d h:i:s a'),
            // 'updated_at' => Carbon::parse($this->updated_at)->format('Y-m-d h:i:s a'),
        ];
        // return parent::toArray($request);
    }
}
