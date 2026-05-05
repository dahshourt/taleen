<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewWorkFlowStatuses extends Model
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

    protected $table = 'new_workflow_statuses';

    protected $fillable = ['new_workflow_id', 'to_status_id', 'default_to_status', 'dependency_ids'];

    protected $casts = [
        'dependency_ids' => 'array',
    ];

    public function to_status()
    {
        // echo "walid";
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    public function workflow()
    {
        return $this->belongsTo(NewWorkFlow::class, 'new_workflow_id');
    }

    /*public function newworkflow()
     {
         return $this->belongsTo(NewWorkFlow::class, 'new_workflow_id', 'id');
     }*/
}
