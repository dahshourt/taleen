<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiRequestTypeField extends Model
{
    use HasFactory;

    protected $fillable = ['request_type_id', 'custom_field_id'];

    public function requestType()
    {
        return $this->belongsTo(RequestType::class);
    }

    public function customField()
    {
        return $this->belongsTo(CustomField::class);
    }
}
