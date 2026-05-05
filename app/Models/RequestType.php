<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function BIFields()
    {
        return $this->belongsToMany(CustomField::class, 'bi_request_type_fields', 'request_type_id', 'custom_field_id');
    }
}
