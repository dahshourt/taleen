<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SrService extends Model
{
    protected $fillable = ['sr_category_id', 'name', 'active'];

    public function category()
    {
        return $this->belongsTo(SrCategory::class, 'sr_category_id');
    }

    public function technicalTeams()
    {
        return $this->belongsToMany(Group::class, 'sr_service_technical_team', 'sr_service_id', 'group_id');
    }

    public function hiddenFields()
    {
        return $this->belongsToMany(CustomField::class, 'sr_service_hidden_fields', 'sr_service_id', 'custom_field_id');
    }
}
