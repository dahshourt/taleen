<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewWorkFlow extends Model
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

    protected $table = 'new_workflow';

    protected $fillable = [
        'previous_status_id',
        'from_status_id',
        'active',
        'same_time',
        'same_time_from',
        'workflow_type', // this is flag especially or not
        'to_status_label',
        'type_id', // this is workflow type id
        'log_message',
    ];

    public function from_status()
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function previous_status()
    {
        return $this->belongsTo(Status::class, 'previous_status_id');
    }

    public function workflowstatus()
    {
        return $this->hasMany(NewWorkFlowStatuses::class, 'new_workflow_id');
    }

    public function type()
    {
        return $this->belongsTo(WorkFlowType::class, 'type_id');
    }

    public function scopeActive($query)
    {
        return $query->whereRaw('CAST(active AS CHAR) = ?', ['1']);
    }

    public function scopeNotActive($query)
    {
        return $query->whereRaw('CAST(active AS CHAR) = ?', ['0']);
    }

    /*public function workflowStatuses()
     {
         return $this->hasMany(NewWorkFlowStatuses::class, 'new_workflow_id', 'id');
     }*/
}
