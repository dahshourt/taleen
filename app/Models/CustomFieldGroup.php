<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $table = 'custom_fields_groups_type';

    protected $fillable = ['form_type', 'active', 'group_id', 'wf_type_id', 'custom_field_id', 'sort', 'validation_type_id', 'enable', 'status_id'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function WorkFlowType()
    {
        return $this->belongsTo(WorkFlowType::class, 'wf_type_id');
    }

    public function CustomField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function ValidationType()
    {
        return $this->belongsTo(ValidationType::class, 'validation_type_id');
    }
}
