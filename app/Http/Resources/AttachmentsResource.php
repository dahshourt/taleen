<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $url = str_replace('/index.php', '', url('/public/uploads'));

        return [
            'id' => $this->id,
            'cr_id' => $this->cr_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'description' => $this->description,
            'file' => $this->file,
            'file_name' => $url . '/' . $this->file_name,
            'path' => $this->path,
            'created_at' => $this->created_at->format('d M Y h:i:s a'),
            'updated_at' => $this->updated_at,
        ];
    }
}
